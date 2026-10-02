<?php

namespace addons\mxmbb\controller;

use think\addons\Controller;

/**
 * MXMBB 前台控制台控制器
 *
 * 路由：
 *  - /addons/mxmbb/index/index      控制台（口令保护）
 *  - /addons/mxmbb/index/switch     一键切换前台模板为 MXMB
 *  - /addons/mxmbb/index/webhook    已启用自动更新时的 POST 更新端点
 */
class Index extends Controller
{
    /**
     * 控制台首页（口令保护）
     */
    public function index()
    {
        $config = $this->getConfig();

        // 口令校验
        $pass = $config['console_pass'];
        if (!empty($pass)) {
            $input = input('pass', '');
            $req   = $this->request->post('pass', $input);
            if ($req !== $pass) {
                return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
                    . '<title>MXMBB 控制台</title><meta name="viewport" content="width=device-width,initial-scale=1">'
                    . '<style>body{background:#f5f6fa;font-family:"Microsoft YaHei",Arial;display:flex;align-items:center;'
                    . 'justify-content:center;height:100vh;margin:0}.box{background:#fff;padding:36px 40px;border-radius:12px;'
                    . 'box-shadow:0 4px 24px rgba(0,0,0,.08);text-align:center;width:340px}'
                    . 'h2{margin:0 0 18px;color:#222}input{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;'
                    . 'margin-bottom:14px;box-sizing:border-box;outline:none}input:focus{border-color:#4e8cff}'
                    . 'button{width:100%;padding:12px;background:#4e8cff;color:#fff;border:none;border-radius:8px;'
                    . 'cursor:pointer;font-size:15px}.tip{color:#999;font-size:12px;margin-top:12px}</style>'
                    . '</head><body><div class="box"><h2>🔒 MXMBB 控制台</h2>'
                    . '<form method="post" action="/addons/mxmbb/index/index.html">'
                    . '<input type="password" name="pass" placeholder="请输入访问口令" autofocus>'
                    . '<button type="submit">进入控制台</button></form>'
                    . '<div class="tip">访问口令在插件配置中设置</div></div></body></html>';
            }
        }

        $this->assign('config', $config);
        $this->assign('update_on', $config['auto_update'] === 'on');
        $tpl = $GLOBALS['config']['site']['template_dir'] ?? '';
        $this->assign('current_tpl', $tpl);
        return $this->fetch('main/index');
    }

    /**
     * 检查更新（返回对比信息）
     */
    public function check()
    {
        $config = $this->getConfig();
        if ($config['auto_update'] !== 'on') {
            return json(['code' => 1, 'msg' => '自动更新未开启']);
        }

        $files = $this->remoteFileList($config);
        if ($files['code'] !== 0) {
            return json(['code' => 1, 'msg' => $files['msg']]);
        }

        // 统计本地缺失
        $missing = [];
        foreach ($files['files'] as $rel => $hash) {
            if (!$this->localFileExists($rel)) {
                $missing[] = $rel;
            }
        }

        return json([
            'code'    => 0,
            'msg'     => '检查完成',
            'data'    => [
                'remote_total' => count($files['files']),
                'local_missing'=> count($missing),
                'missing'      => array_slice($missing, 0, 20),
            ],
        ]);
    }

    /**
     * 一键更新：从仓库拉取文件并覆盖到本地
     */
    public function update()
    {
        ini_set('max_execution_time', '300');
        set_time_limit(300);

        $config = $this->getConfig();
        if ($config['auto_update'] !== 'on') {
            return json(['code' => 1, 'msg' => '自动更新未开启']);
        }

        $files = $this->remoteFileList($config);
        if ($files['code'] !== 0) {
            return json(['code' => 1, 'msg' => $files['msg']]);
        }

        $scope    = isset($config['update_scope']) ? $config['update_scope'] : 'all';
        $repoRaw  = $this->repoRawBase($config);
        $username = 'ssmhdssmhd';
        $repoName = 'MXPGCMS';
        $branch   = $config['repo_branch'];

        $success = 0;
        $fail    = [];
        foreach ($files['files'] as $rel => $hash) {
            // 按范围过滤
            if ($scope === 'template' && strpos($rel, 'template/') !== 0) {
                continue;
            }
            if ($scope === 'addon' && strpos($rel, 'addons/mxmbb/') !== 0) {
                continue;
            }

            $rawUrl = $repoRaw . '/' . $username . '/' . $repoName . '/' . $branch . '/' . $rel;
            $content = $this->httpGet($rawUrl);
            if ($content === false) {
                $fail[] = $rel;
                continue;
            }
            $local = ROOT_PATH . str_replace('template/', 'template/', $rel);
            // 仅覆盖 template/ 与 addons/mxmbb/ 下的文件
            if (strpos($rel, 'template/') !== 0 && strpos($rel, 'addons/mxmbb/') !== 0) {
                continue;
            }
            if (strpos($rel, 'template/') === 0 || strpos($rel, 'addons/mxmbb/') === 0) {
                if ($this->writeFile($local, $content)) {
                    $success++;
                } else {
                    $fail[] = $rel;
                }
            }
        }

        // 清理缓存
        $this->clearCache();

        return json([
            'code' => 0,
            'msg'  => '更新完成',
            'data' => ['success' => $success, 'fail' => $fail],
        ]);
    }

    /**
     * 一键切换前台模板为 MXMB
     */
    public function switch()
    {
        $config = $this->getConfig();
        $name   = $config['template_name'];

        $tplDir = ROOT_PATH . 'template/' . $name . '/info.ini';
        if (!is_file($tplDir)) {
            return json(['code' => 1, 'msg' => 'MXMB模板不存在：template/' . $name]);
        }

        // 修改系统模板配置
        $file = APP_PATH . 'extra/maccms.php';
        $cfg  = include $file;
        if (!isset($cfg['site'])) {
            return json(['code' => 1, 'msg' => '系统配置结构异常']);
        }
        $cfg['site']['template_dir']    = $name;
        $cfg['site']['mob_template_dir'] = $name;

        $res = mac_arr2file($file, $cfg);
        $this->clearCache();
        if ($res === false) {
            return json(['code' => 1, 'msg' => '写配置文件失败']);
        }
        return json(['code' => 0, 'msg' => '已切换为 MXMB 模板']);
    }

    /**
     * 清理缓存
     */
    public function refresh()
    {
        $this->clearCache();
        return json(['code' => 0, 'msg' => '缓存已清理']);
    }

    protected function getConfig()
    {
        $cfg = get_addon_config('mxmbb');
        return is_array($cfg) ? $cfg : [];
    }

    private function localFileExists($rel)
    {
        $local = ROOT_PATH . $rel;
        return is_file($local);
    }

    private function writeFile($path, $content)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $tmp = $path . '.tmp.mxmbb';
        if (file_put_contents($tmp, $content) === false) {
            return false;
        }
        return @rename($tmp, $path);
    }

    private function clearCache()
    {
        $runtime = ROOT_PATH . 'runtime/';
        if (is_dir($runtime)) {
            foreach (['cache', 'temp'] as $sub) {
                $dir = $runtime . $sub;
                if (is_dir($dir)) {
                    $this->recursiveDelete($dir);
                    @mkdir($dir, 0755, true);
                }
            }
        }
    }

    private function recursiveDelete($dir)
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_dir($f)) {
                $this->recursiveDelete($f);
            } else {
                @unlink($f);
            }
        }
    }

    /**
     * 获取仓库文件清单：优先用 info.ini / README 约定的 manifest；
     * 简化实现为从 GitHub API 获取树。
     */
    private function remoteFileList($config)
    {
        $username = 'ssmhdssmhd';
        $repoName = 'MXPGCMS';
        $branch   = $config['repo_branch'];

        $api = "https://api.github.com/repos/{$username}/{$repoName}/git/trees/{$branch}?recursive=1";
        $resp = $this->httpGet($api, [
            'User-Agent: MXMBB-Update',
            'Accept: application/vnd.github+json',
        ]);
        if ($resp === false) {
            return ['code' => 1, 'msg' => '无法连接 GitHub，检查仓库地址'];
        }
        $data = json_decode($resp, true);
        if (empty($data['tree'])) {
            return ['code' => 1, 'msg' => '未能读取仓库目录'];
        }

        $files = [];
        foreach ($data['tree'] as $item) {
            if ($item['type'] !== 'blob') {
                continue;
            }
            $path = $item['path'];
            if (strpos($path, 'template/') === 0 || strpos($path, 'addons/mxmbb/') === 0) {
                $files[$path] = $item['sha'];
            }
        }
        if (empty($files)) {
            return ['code' => 1, 'msg' => '仓库中未发现 MXMB模板/MXMBB插件文件'];
        }
        return ['code' => 0, 'files' => $files];
    }

    private function repoRawBase($config)
    {
        return 'https://raw.githubusercontent.com';
    }

    private function httpGet($url, $headers = [])
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'MXMBB-Update',
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            return false;
        }
        return $body;
    }
}