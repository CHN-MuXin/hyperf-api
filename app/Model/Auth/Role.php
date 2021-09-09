<?php

declare(strict_types=1);

namespace App\Model\Auth;

use App\Model\Model;

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

        return $query->first();
    }

}