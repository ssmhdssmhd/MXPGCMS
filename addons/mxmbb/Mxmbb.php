<?php

namespace addons\mxmbb;

use think\Addons;

/**
 * MXMBB 功能插件
 *
 * 功能：
 *  1. 模板管理：一键把 MacCMS 前台模板切换为 MXMB 模板
 *  2. 自动更新：从 GitHub MXPGCMS 仓库 main 分支拉取 MXMB 模板 + MXMBB 插件最新代码，
 *     覆盖到当前服务器并清理缓存，站点管理员免手动更新。
 *  3. 前台插件控制台：/addons/mxmbb/index/index
 */
class Mxmbb extends Addons
{
    public $info = [
        'name'    => 'mxmbb',
        'title'   => 'MXMBB功能插件',
        'intro'   => 'MXMBB - 模板管理与自动更新',
        'author'  => '射手沫蝴蝶(MX)',
        'version' => '0.1.1',
        'state'   => 1,
    ];

    /**
     * 安装
     */
    public function install()
    {
        return true;
    }

    /**
     * 卸载
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 启用
     */
    public function enable()
    {
        return true;
    }

    /**
     * 停用
     */
    public function disable()
    {
        return true;
    }

    /**
     * Hook: app_init —— 仅当请求指向本插件时开启 ThinkPHP 路由，
     * 使 /addons/mxmbb/... 正常解析到本插件控制器。
     */
    public function appInit()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (strpos($uri, 'addons/mxmbb') !== false) {
            \think\App::route(true);
        }
    }
}