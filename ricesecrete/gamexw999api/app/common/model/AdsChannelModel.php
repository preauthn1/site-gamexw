<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class AdsChannelModel extends Model
{
    protected $name = 'ad_channel';
    protected $pk = 'ac_id';
}
