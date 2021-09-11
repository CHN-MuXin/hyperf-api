<?php

declare(strict_types=1);

namespace App\Model\Auth;

use App\Model\Model;
use Donjan\Casbin\Enforcer;

/**
 * 角色模型类
 * Class Role
 * @package App\Model\Auth
 * @Author YiYuan-Lin
 * @Date: 2021/1/21
 */
class Role extends Model
{
    /**
     * 声明超级管理员角色名
     */
    const SUPER_ADMIN = 'super_admin';

    protected $fillable = ["name","guard_name","description"];

    protected $attributes = [
        'guard_name' => '',
    ];

    /**
     * 根据角色ID获取角色信息
     * @param $id
     * @return array|\Hyperf\Database\Model\Builder|\Hyperf\Database\Model\Model|object|null
     */
    static function getOneByRoleId($id)
    {
        if (empty($id)) return [];

        $query = static::query();
        $query = $query->where('id', $id);

        $data = $query->first();
        $list = Enforcer::getPermissionsForUser('role_'.$id);
        $o_Permissions=[];
        foreach ($list as $value) {
            $o_Permissions[]=$value[1];
        }
        $data->permissions = Permission::query()->whereIn('name',$o_Permissions)->get();

        return $data;
    }

    /**
     * 分配角色权限
     * @param array $Permissions 权限名称
     * @return array
    */
    public function syncPermissions($Permissions){
        $return = Enforcer::getPermissionsForUser( 'role_'.$this->id);
        $o_Permissions=[];
        foreach ($return as $value) {
            $o_Permissions[]=$value[1];
        }
        $del = array_diff($o_Permissions ,$Permissions);
        $add = array_diff($Permissions,$o_Permissions);
        foreach ($del as  $Permission) {
            Enforcer::deletePermissionForUser('role_'.$this->id,$Permission);
        }
        foreach ($add as  $Permission) {
            Enforcer::addPermissionForUser( 'role_'.$this->id,$Permission);
        }
        return true;
    }
}