<?php

namespace app\api\factory_v2;

use app\common\model\IndexMenuModel;
use app\common\service\AgentService;
use think\facade\Lang;
use think\facade\Request;

class IndexFactory
{
    private $param;//请求参数
    private $lang;

    public function __construct($uid = 0)
    {
        $this->uid = $uid;
        $this->param = Request::param();
        $this->lang = strtoupper(Lang::getLangSet());
    }

    //首页菜单列表
    public function menu_run()
    {
        $return = [];
        $return['code'] = 1;
        // 代理查询
        $agent_where = AgentService::getAgentWhereUid('agent_id', true, '', $this->uid);
        // 获取菜单数据
        $menus = $this->getMenus(0, $agent_where);
        $data = [];
        if (!empty($menus)) {

            foreach ($menus as $key => $value) {
                // 根据区域构建菜单数据
                if (!isset($data[$value['area']])) {
                    $data[$value['area']] = [];
                }
                // 获取菜单项
                $row = $this->getMenuItem($value);
                // 获取子菜单数据
                $row['children'] = $this->getChildMenu($value['children']);
                if (strrpos($row['href'], 'home') !== false) {
                    $row['children'] = $this->getChildMenuHome($row['children']);
                }

                if (strrpos($row['href'], '/gamePage') !== false) {
                    $temp_key = $data[$value['area']] ? $data[$value['area']] : [];
                    if (!empty($temp_key)) {
                        $index = array_keys($temp_key)[count($temp_key) - 1];
                        $row['href'] = '/gamePage/' . ($index + 1);
                    }
                }
                $data[$value['area']][] = $row;
            }
        }
        $return['data'] = $data;
        return $return;
    }

    // 获取菜单函数，根据父id、语言和条件获取菜单数据
    public function getMenus($pid = 0, $where = null)
    {
        $map = [
            'status' => 1,
            'pid' => $pid,
        ];
        // 构建查询条件

        $menus = IndexMenuModel::where($map)->where($where)->append(['children'])->order('sort ASC,id ASC')->select();
        // 根据条件从数据库中获取菜单数据，并按照排序顺序进行排序

        if ($pid == 0 && $menus->isEmpty()) {
            $menus = IndexMenuModel::where($map)->where($where)->order('sort ASC,id ASC')->select();
            // 当父id为0且当前语言下无菜单数据时，切换为默认语言再次查询菜单数据
        }
        if (!$menus->isEmpty()) {
            unset($map['pid']);
            $child_menus = IndexMenuModel::where($map)->whereIn('pid', $menus->column('id'))->where($where)->order('sort ASC,id ASC')->select();
            // 如果菜单数据不为空，获取子菜单数据

            $tmp = [];
            foreach ($child_menus as $child_menu) {
                $tmp[$child_menu['pid']][] = $child_menu;
            }
            // 将子菜单按照父id进行分组存储

            $menus->each(function ($item) use ($tmp) {
                if (isset($tmp[$item->id])) {
                    $item->children = $tmp[$item->id];
                }
                return $item;
            });
            // 将子菜单数据以children字段存储到对应的父菜单下
        }
        return $menus;
        // 返回查询到的菜单数据
    }


    /**
     * @param $menu
     * @return array
     */
    private function getMenuItem($menu)
    {
        return [
            'name' => $menu['name'],
            'href' => $menu['href'],
            'is_img' => $menu['is_img'],
            'icon' => $menu['icon'],
            'icon_url' => $menu['icon_url'],
            'select_icon_url' => $menu['select_icon_url'],
            'show_img' => $menu['show_img'],
            'show_bg1' => $menu['show_bg1'],
            'show_bg2' => $menu['show_bg2'],
            'logo' => $menu['logo'],
            'extra' => $menu['extra']
        ];
    }

    private function getChildMenu($subMenus)
    {
        if (empty($subMenus)) {
            return [];
        }
        $data = [];
        foreach ($subMenus as $value) {
            $data[] = $this->getMenuItem($value);
        }

        return $data;
    }

    /**
     * 获取首页菜单
     * @param $subMenus
     * @return array
     */
    private function getChildMenuHome($subMenus)
    {
        $subMenusList = [];
        foreach ($subMenus as $key => $value) {
            if (isset($subMenusList[$value['href']])) {
                $subMenusList[$value['href']][] = $value;
            } else {
                $subMenusList[$value['href']] = [];
                $subMenusList[$value['href']][] = $value;
            }
        }
        $out_subMenusList = [];
        foreach ($subMenusList as $key => $value) {
            $out_subMenusList[] = $value;
        }
        return $out_subMenusList;
    }
}