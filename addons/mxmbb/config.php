<?php

return array(
    // 远程仓库配置
    array(
        'name'    => 'repo_url',
        'title'   => '更新仓库地址',
        'type'    => 'string',
        'content' => array(),
        'value'   => 'https://github.com/ssmhdssmhd/MXPGCMS',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => 'MXMB模板与MXMBB插件的GitHub仓库地址',
        'ok'      => '',
        'extend'  => '',
    ),
    array(
        'name'    => 'repo_branch',
        'title'   => '更新分支',
        'type'    => 'string',
        'content' => array(),
        'value'   => 'main',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '从该分支拉取最新代码',
        'ok'      => '',
        'extend'  => '',
    ),
    // 自动更新开关
    array(
        'name'    => 'auto_update',
        'title'   => '启用自动更新',
        'type'    => 'select',
        'content' => array(
            'on'  => '开启',
            'off' => '关闭',
        ),
        'value'   => 'on',
        'rule'    => '',
        'msg'     => '',
        'tip'     => '开启后在后台控制台显示「检查更新/一键更新」按钮',
        'ok'      => '',
        'extend'  => '',
    ),
    // 更新范围
    array(
        'name'    => 'update_scope',
        'title'   => '更新范围',
        'type'    => 'select',
        'content' => array(
            'all'        => '模板 + 插件',
            'template'   => '仅模板',
            'addon'      => '仅插件',
        ),
        'value'   => 'all',
        'rule'    => '',
        'msg'     => '',
        'tip'     => '一键更新覆盖范围',
        'ok'      => '',
        'extend'  => '',
    ),
    // 模板切换配置
    array(
        'name'    => 'template_name',
        'title'   => 'MXMB模板目录名',
        'type'    => 'string',
        'content' => array(),
        'value'   => 'mxmb',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => 'MXMB 模板在 template/ 下的目录名，用于一键切换前台模板',
        'ok'      => '',
        'extend'  => '',
    ),
    // 控制台访问口令（可为空则不校验）
    array(
        'name'    => 'console_pass',
        'title'   => '控制台访问口令',
        'type'    => 'string',
        'content' => array(),
        'value'   => 'mxmbb',
        'rule'    => '',
        'msg'     => '',
        'tip'     => '访问 /addons/mxmbb/index/index 时需填写的口令；留空则不校验',
        'ok'      => '',
        'extend'  => '',
    ),
);