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

        $root = $this->fetchRepo($config);
        if ($root === false) {
            return json(['code' => 1, 'msg' => '无法连接 GitHub，检查仓库地址']);
        }

        $files  = $this->listRemoteFiles($root);
        $missing = [];
        foreach ($files as $rel) {
            if (!$this->localFileExists($rel)) {
                $missing[] = $rel;
            }
        }
        $this->cleanupRepo($root);

        return json([
            'code'    => 0,
            'msg'     => '检查完成',
            'data'    => [
                'remote_total' => count($files),
                'local_missing'=> count($missing),
                'missing'      => array_slice($missing, 0, 20),
            ],
        ]);
    }

    /**
     * 一键更新：从仓库拉取代码并覆盖到本地
     */
    public function update()
    {
        ini_set('max_execution_time', '300');
        set_time_limit(300);

        $config = $this->getConfig();
        if ($config['auto_update'] !== 'on') {
            return json(['code' => 1, 'msg' => '自动更新未开启']);
        }

        $root = $this->fetchRepo($config);
        if ($root === false) {
            return json(['code' => 1, 'msg' => '无法连接 GitHub，检查仓库地址']);
        }

        $scope   = isset($config['update_scope']) ? $config['update_scope'] : 'all';
        $success = 0;
        $fail    = [];

        foreach (['template', 'addons/mxmbb'] as $dir) {
            if ($scope === 'template' && $dir !== 'template') {
                continue;
            }
            if ($scope === 'addon' && $dir !== 'addons/mxmbb') {
                continue;
            }
            $src = $root . '/' . $dir;
            if (!is_dir($src)) {
                continue;
            }
            $this->copyDir($src, ROOT_PATH . $dir, $success, $fail);
        }

        $this->cleanupRepo($root);

        // 清理缓存
        $this->clearCache();

        if (empty($success) && !empty($fail)) {
            return json(['code' => 1, 'msg' => '更新失败', 'data' => ['success' => $success, 'fail' => $fail]]);
        }
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
     * 下载并解压仓库 tarball，返回解压后的仓库根目录；
     * 失败返回 false。使用 GitHub Codeload 规避 API 限流。
     */
    private function fetchRepo($config)
    {
        $username = 'ssmhdssmhd';
        $repoName = 'MXPGCMS';
        $branch   = $config['repo_branch'];
        $url      = "https://codeload.github.com/{$username}/{$repoName}/tar.gz/refs/heads/{$branch}";

        $body = $this->httpGet($url);
        if ($body === false) {
            return false;
        }

        $tmpDir = ROOT_PATH . 'runtime/mxmbb_repo_' . time();
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }
        $tarGz = $tmpDir . '/repo.tar.gz';
        if (file_put_contents($tarGz, $body) === false) {
            @rmdir($tmpDir);
            return false;
        }

        try {
            $phar = new \PharData($tarGz);
            $phar->decompress(); // 生成 repo.tar
            $tar = $tmpDir . '/repo.tar';
            if (!is_file($tar)) {
                throw new \Exception('decompress failed');
            }
            $extract = $tmpDir . '/extract';
            @mkdir($extract, 0755, true);
            $phar = new \PharData($tar);
            $phar->extractTo($extract, null, true);
        } catch (\Exception $e) {
            $this->recursiveDelete($tmpDir);
            return false;
        }

        $root = $this->findRepoRoot($extract);
        if ($root === false) {
            $this->recursiveDelete($tmpDir);
            return false;
        }
        return $root;
    }

    /**
     * 在解压目录中定位仓库根目录（顶层目录名形如 MXPGCMS-main）
     */
    private function findRepoRoot($extract)
    {
        foreach (glob($extract . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (is_dir($dir . '/template') || is_dir($dir . '/addons')) {
                return $dir;
            }
        }
        // 解压目录本身就是根
        if (is_dir($extract . '/template') || is_dir($extract . '/addons')) {
            return $extract;
        }
        return false;
    }

    /**
     * 列出仓库根目录下 template/ 与 addons/mxmbb/ 的全部文件相对路径
     */
    private function listRemoteFiles($root)
    {
        $files = [];
        foreach (['template', 'addons/mxmbb'] as $dir) {
            $src = $root . '/' . $dir;
            if (!is_dir($src)) {
                continue;
            }
            $this->collectFiles($src, $dir, $files);
        }
        return $files;
    }

    private function collectFiles($dir, $prefix, &$files)
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_dir($f)) {
                $this->collectFiles($f, $prefix . '/' . basename($f), $files);
            } else {
                $files[] = $prefix . '/' . basename($f);
            }
        }
    }

    /**
     * 递归复制目录到目标（保留相对路径），统计成功/失败
     */
    private function copyDir($src, $dst, &$success, &$fail)
    {
        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }
        foreach (glob($src . '/*') ?: [] as $f) {
            $target = $dst . '/' . basename($f);
            if (is_dir($f)) {
                $this->copyDir($f, $target, $success, $fail);
            } else {
                if ($this->writeFile($target, file_get_contents($f))) {
                    $success++;
                } else {
                    $fail[] = str_replace(ROOT_PATH, '', $target);
                }
            }
        }
    }

    /**
     * 清理临时下载目录
     */
    private function cleanupRepo($root)
    {
        $tmpDir = dirname(dirname($root));
        if (strpos($tmpDir, ROOT_PATH . 'runtime/mxmbb_repo_') === 0) {
            $this->recursiveDelete($tmpDir);
        }
    }

    private function httpGet($url, $headers = [])
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'MXMBB-Update',
            CURLOPT_HTTPHEADER     => $headers,
        ];
        // 读取环境代理（服务器需走代理访问 GitHub 时自动生效）
        $proxy = getenv('HTTPS_PROXY') ?: getenv('https_proxy')
               ?: getenv('HTTP_PROXY') ?: getenv('http_proxy');
        if (!empty($proxy)) {
            $opts[CURLOPT_PROXY] = $proxy;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            return false;
        }
        return $body;
    }
}