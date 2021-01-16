<?php
namespace App\Http\Service\Auth;

use App\Constants\StatusCode;
use App\Foundation\Traits\Singleton;
use App\Http\Service\BaseService;
use App\Model\Auth\Permission;
use App\Model\Auth\User;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\ApplicationContext;
use Phper666\JWTAuth\JWT;

/**
 * 登陆服务基础类
 * Class LoginService
 * @package App\Http\Service\Auth
 * @Author YiYuan-Lin
 * @Date: 2020/10/29
 */
class LoginService extends BaseService
{
    use Singleton;

    /**
     * @Inject()
     * @var JWT
     */
    private $jwt;

    /**
     * 处理登陆逻辑
     * @param array $params
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function login(array $params) : array
    {
        //获取用户信息
        $user = User::query()->where('username', $params['username'])->first();

        //检查用户以及密码是否正确以及检查账户是否被停用
        if (empty($user)) $this->throwExp(StatusCode::ERR_USER_ABSENT,'登录失败，用户不存在');
        if (md5($params['password']) != $user->password) $this->throwExp(StatusCode::ERR_USER_PASSWORD,'登录失败，用户验证失败，密码错误');
        if ($user['status'] != 1)  $this->throwExp(StatusCode::ERR_USER_DISABLE,'该账户已经被停用，请联系管理员');

        //校验验证码 若是测试环境跳过验证码验证
        if (!env('APP_TEST')) {
            $container = ApplicationContext::getContainer();
            $redis = $container->get(\Hyperf\Redis\Redis::class);
            $code = $redis->get($params['code_key']);
            if (strtolower($params['captcha']) != strtolower($code)) $this->throwExp(StatusCode::ERR_CODE, '验证失败，验证码错误');
        }
        $userData = [
            'uid' => $user->id, //如果使用单点登录，必须存在配置文件中的sso_key的值，一般设置为用户的id
            'username' => $user->username,
        ];
        $token = $this->jwt->getToken($userData);

        //更新用户信息
        $user->last_login = time();
        $user->last_ip = getClientIp($this->request);
        $user->save();
        $responseData = $this->respondWithToken($token);

        return $responseData;
    }

    /**
     * 登陆初始化，获取用户信息以及一些权限菜单
     * @return mixed
     */
    public function initialization() : array
    {
        $responseData = [];
        //获取用户信息
        $user = UserService::getInstance()->getUserInfoByToken();
        $userInfo = objToArray($user);
        unset($userInfo['roles']);
        unset($userInfo['permissions']);

        $menu = $this->getMenuList($user);
        $responseData['user_info'] = objToArray($userInfo);
        $responseData['role_info'] = $user->getRoleNames();
        $responseData['menu_header'] = $menu['menuHeader'];
        $responseData['menu_list'] = $menu['menuList'];
        $responseData['permission'] = $menu['permission'];

        return $responseData;
    }

    /**
     * 处理TOKEN数据
     * @param $token
     * @return array
     */
    protected function respondWithToken(string $token) : array
    {
        $data = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' =>  $this->jwt->getTTL(),
        ];
        return $data;
    }

    /**
     * 获取头部菜单数据以及菜单列表
     * @param object $user
     * @return array
     */
    protected function getMenuList(object $user) : array
    {
        //获取菜单树形
        $menuList = Permission::getUserMenuList($user);
        $permission = $user->getAllPermissions();
        $menuHeader = [];
        foreach ($menuList as $key => $val) {
            if ($val['status'] != 0) {
                $menuHeader[] = [
                    'title' => $val['display_name'],
                    'icon' => $val['icon'],
                    'path' => $val['url'],
                    'name' => $val['name'],
                    'id' => $val['id'],
                    'type' => $val['type'],
                    'sort' => $val['sort'],
                ];
            }
        }
        //排序
        array_multisort($menuHeader, SORT_ASC,array_column($menuHeader, 'sort'));

        return [
            'menuList' => $menuList,
            'menuHeader' => $menuHeader,
            'permission' => array_column(objToArray($permission), 'name'),
        ];
    }
}
