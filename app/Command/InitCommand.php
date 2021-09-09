<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\Auth\User;
use App\Model\System\DictData;
use App\Model\System\DictType;
use App\Model\System\GlobalConfig;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use App\Model\Auth\Permission;
use App\Model\Auth\Role;

/**
 * @Command
 */
class InitCommand extends HyperfCommand
{
    /**
     * 执行的命令行
     *
     * @var string
     */
    protected $name = 'init';

    public function configure()
    {
        parent::configure();
        $this->setHelp('HyperfCms 项目初始化');
        $this->setDescription('HyperfCms 项目初始化');
    }

    /**
     * 命令执行方法
     */
    public function handle()
    {
        $roles = [
            [
                'name' => 'super_admin',
                'guard_name' => 'web',
                'description' => '超级管理员'
            ]
            ,[
                'name' => 'default_admin',
                'guard_name' => 'web',
                'description' => '普通管理员'
            ]
            ,[
                'name' => 'tourist_admin',
                'guard_name' => 'web',
                'description' => '游客'
            ]
        ];

        foreach ($roles as $key => $role) {
             //创建默认角色
            $r = Role::query()->where('name', $role['name'])->first();
            if($r){
                $this->line('角色已经存在 '.$role['description'].'----------------------------');
                continue;
            }
            Role::create($role);
        }

        //创建权限
        $permissionList = config('permissionData');
        $this->InitPermission($permissionList);

        //创建用户
        $users=[
            [
                'username'  => 'admin@admin.com',
                'password'  => 'admin@admin.com',
                'status'    => User::STATUS_ON,
                'desc'      => '超级用户',
                'mobile'    => '1800000000',
                'sex'       => User::SEX_BY_MALE,
                'email'     => 'admin@admin.com',
                'avatar'    => 'https://shmily-album.oss-cn-shenzhen.aliyuncs.com/admin_face/face' . rand(1, 10) .'.png',
                'roles'     => [
                    'super_admin'
                ],
            ]
        ];

        foreach ($users as $key => $value) {
            $user = User::query()->where('username',$value['username'])->first();
            if ( $user ) {
                $this->line('用户' .$value['username']. '已经创建' . PHP_EOL, 'warning');
            }else{
                $user = new User();
                $user->username     = $value['username'];
                $user->password     = md5($value['password']);
                $user->status       = $value['status'];
                $user->last_login   = time();
                $user->desc         = $value['desc'];
                $user->mobile       = $value['mobile'];
                $user->sex          = $value['sex'];
                $user->email        = $value['email'];
                $user->avatar       = $value['avatar'];
                $user->save();
            }
            //添加默认角色到默认用户
            foreach ($value['roles'] as  $role) {
                $user->assignRole($role);
            }
            $this->line('初始化用户成功' . PHP_EOL . '默认用户名：'.$value['username'] . PHP_EOL . '默认密码：' . $value['password'] . PHP_EOL, 'info');
        }

        //初始化字典数据
        $dictList = config('dictData');
        $this->InitDict($dictList);

        $globalConfigList = config('globalConfig.global_config');
        foreach ($globalConfigList as $globalConfig) {
            if (empty(GlobalConfig::query()->find($globalConfig['id']))) GlobalConfig::query()->insert($globalConfig);
        }
        $this->line('初始化全局参数成功', 'info');
    }

    public function InitPermission(array $PermissionList,$pid = 0)
    {
        foreach ($PermissionList as  $v) {
            $p = Permission::query()->where('name', $v['name'])->first();
            if(!$p){
                $p = new Permission();
                $p->parent_id       = $pid;
                $p->name            = $v['name'];
                $p->display_name    = $v['display_name'];
                $p->display_desc    = $v['display_desc'];
                $p->url             = $v['url'];
                $p->component       = $v['component'];
                $p->guard_name      = $v['guard_name'];
                $p->icon            = $v['icon'];
                $p->type            = $v['type'];
                $p->hidden          = $v['hidden'];
                $p->status          = $v['status'];
                $p->sort            = $v['sort'];
                if( !$p->save() )
                    continue;
                $this->line('添加权限成功----------------------------' . $v['display_name']);
            }else{
                $this->line('权限已存在----------------------------' . $v['display_name']);
            }
            if ( isset($v['subfield']) )
                $this->InitPermission($v['subfield'],$p->id);
        }
    }

    public function InitDict(array $DictList)
    {
        foreach ($DictList as  $v) {
            $p = DictType::query()->where('dict_type', $v['dict_type'])->first();
            if(!$p){
                $p = new DictType();
                $p->dict_type    = $v['dict_type'];
                $p->dict_name    = $v['dict_name'];
                $p->remark       = $v['remark'];
                $p->status       = $v['status'];
                if( !$p->save() )
                    continue;
            }
            if( is_array($v['dict_data']) ){
                foreach ($v['dict_data'] as $vv) {
                    $p = DictData::query()->where('dict_type', $v['dict_type'])->where('dict_label',$vv['dict_label'])->first();
                    if(!$p){
                        $p = new DictData();
                        $p->dict_sort    = $vv['dict_sort'];
                        $p->dict_label   = $vv['dict_label'];
                        $p->dict_value   = $vv['dict_value'];
                        $p->dict_type    = $v['dict_type'];
                        $p->css_class    = $vv['css_class'];
                        $p->list_class   = $vv['list_class'];
                        $p->is_default   = $vv['is_default'];
                        $p->status       = $vv['status'];
                        $p->remark       = $vv['remark'];
                        if( !$p->save() )
                            continue;
                    }
                }
            }
        }
        $this->line('初始化字典数据成功', 'info');
    }

}