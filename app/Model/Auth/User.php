<?php

declare(strict_types=1);

namespace App\Model\Auth;

use App\Model\Laboratory\FriendRelation;
use App\Model\Model;
use App\Task\Laboratory\FriendWsTask;
use Hyperf\Database\Model\Events\Created;
use Hyperf\Database\Model\Events\Deleted;
use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;
use Donjan\Casbin\Enforcer;

class User extends Model
{
    /**
     * @Inject()
     * @var ContainerInterface
     */
    protected $container;


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'default';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * 定义状态枚举
     */
    const STATUS_ON = 1;
    const STATUS_OFF= 0;

    /**
     * 定义性别
     */
    const SEX_BY_MALE = 1;
    const SEX_BY_Female = 0;

    /**
     * 根据用户ID获取用户信息
     * @param $id
     * @return array|\Hyperf\Database\Model\Builder|\Hyperf\Database\Model\Model|object|null
     */
    static function getOneByUid($id)
    {
        if (empty($id)) return [];

        $query = static::query();
        $query = $query->where('id', $id);

        return $query->first();
    }

    /**
     * 监听用户添加事件
     * @param Created $event
     */
    public function created(Created $event)
    {
        $currentUser = $event->getModel();
        $userList = User::query()->where('id', '!=', $currentUser['id'])->get()->pluck('id');

        foreach ($userList as $user_id) {
            FriendRelation::insert([
                'uid' => $currentUser['id'],
                'friend_id' => $user_id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        //维护其他用户好友关系
        $this->container->get(FriendWsTask::class)->maintainFriendRelation($currentUser);
    }

    /**
     * 监听用户删除事件
     * @param Deleted $event
     */
    public function deleted(Deleted $event)
    {
        $currentUser = $event->getModel();
        //维护其他用户好友关系
        $this->container->get(FriendWsTask::class)->deleteContactEvent($currentUser);
    }


    /**
     * 判断权限
     * @param string $role 权限名称
     * @return bool
     */
    public function hasRole(string $role){
        return Enforcer::hasRoleForUser($this->id,$role);
    }
    /**
     * 添加角色给用户
     * @param string $role 权限名称
     * @return bool
     */
    public function assignRole(string $role){
        $return = Enforcer::addRoleForUser( $this->id, $role);
        return true;
    }
    /**
     * 获取用户角色
     * @return array
     */
    public function getRoleNames(){
        $return = Enforcer::getRolesForUser( $this->id);
        return $return;
    }
    /**
     * 获取用户权限
     * @return array
     */
    public function getPermissionNames(){
        $return = Enforcer::getPermissionsForUser( $this->id);
        return $return;
    }
    /**
     * 同步角色
     * @param array $roles 权限名称
     * @return array
     */
    public function syncRoles($roles){
        $return = Enforcer::getRolesForUser( $this->id);
        $del = array_diff($return ,$roles);
        $add = array_diff($roles,$return);
        foreach ($del as  $d) {
            Enforcer::deleteRoleForUser($this->id,$d);
        }
        foreach ($add as  $role) {
            Enforcer::addRoleForUser( $this->id,$role);
        }
        return true;
    }
    /**
     * 分配用户权限
     * @param array $Permissions 权限名称
     * @return array
     */
    public function syncPermissions($Permissions){
        $return = Enforcer::getPermissionsForUser( $this->id);
        $del = array_diff($return ,$Permissions);
        $add = array_diff($Permissions,$return);
        foreach ($del as  $d) {
            Enforcer::deletePermissionForUser($this->id,$d);
        }
        foreach ($add as  $Permission) {
            Enforcer::addPermissionForUser( $this->id,$Permission);
        }
        return true;
    }
}