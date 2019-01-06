<?php

/**
 * Typecho 微信助手
 * 对冰剑(https://github.com/binjoo/WeChatHelper)进行精简的版本。
 *
 * @package WeChatHelper
 * @author Holmesian
 * @version 2.2.2
 * @link https://holmesian.org
 */
class WeChatHelper_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Helper::addAction('wechatHelper', 'WeChatHelper_Action');
        Helper::addRoute('wechat', '/wechat', 'WeChatHelper_Action', 'link');
        $db = Typecho_Db::get();
        if ("Pdo_Mysql" === $db->getAdapterName() || "Mysql" === $db->getAdapterName()) {
            /**
             * 创建自定义菜单表
             */
            $db->query("CREATE TABLE IF NOT EXISTS " . $db->getPrefix() . 'wch_menus' . " (
                      `mid` int(11) NOT NULL AUTO_INCREMENT,
                      `level` varchar(10) DEFAULT 'button',
                      `name` varchar(200) DEFAULT '',
                      `type` varchar(10) DEFAULT 'view',
                      `value` varchar(200) DEFAULT '',
                      `sort` int(3) DEFAULT '0',
                      `order` int(3) DEFAULT '1',
                      `parent` int(11) DEFAULT '0',
                      `created` int(10) DEFAULT '0',
                      PRIMARY KEY (`mid`)
                    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1");
            $db->query("INSERT INTO `" . $db->getPrefix() . 'wch_menus' . "` (`mid`, `level`, `name`, `type`, `value`, `sort`, `order`, `parent`, `created`) VALUES
(1, 'button', '首页', 'view', 'https://holmesian.org/', 10, 1, 0, 1503804104),
(3, 'button', '搜索', 'view', 'https://m.holmesian.org/', 20, 2, 0, 1503804141),
(4, 'button', '其他', 'click', NULL, 50, 5, 0, 1503804153),
(6, 'sub_button', '最新文章', 'click', 'n', 51, 1, 4, 1503804247),
(7, 'sub_button', '随机文章', 'click', 'r', 52, 2, 4, 1503807202),
(8, 'sub_button', '手气不错', 'click', 'l', 54, 4, 4, 1503824995);");

	    $db->query($db->sql()->insert('table.options')->rows(array("name"=>"WCH_access_token","user"=>"0","value"=>"0")));
	    $db->query($db->sql()->insert('table.options')->rows(array("name"=>"WCH_expires_in","user"=>"0","value"=>"0")));

        } else {
            throw new Typecho_Plugin_Exception(_t('对不起, 本插件仅支持MySQL数据库。'));
        }

        Helper::addAction('WeChat', 'WeChatHelper_Action');
        Helper::addPanel(1, 'WeChatHelper/Page/Menus.php', '公众号菜单', '公众号菜单', 'administrator');

        return ('微信助手已经成功激活，请进入设置Token!');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        $db = Typecho_Db::get();
        $options = Typecho_Widget::widget('Widget_Options');
        if ("Pdo_Mysql" === $db->getAdapterName() || "Mysql" === $db->getAdapterName()) {
            $db->query("drop table " . $db->getPrefix() . "wch_menus");
            $db->query($db->sql()->delete('table.options')->where('name like ?', "WCH_%"));
        }
        Helper::removePanel(1, 'WeChatHelper/Page/Menus.php');

        Helper::removeRoute('wechat');
        Helper::removeAction('wechatHelper');
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {

        /** 用户添加订阅欢迎语 **/
        $welcome = new Typecho_Widget_Helper_Form_Element_Textarea('welcome', NULL, '欢迎！' . chr(10) . '发送\'h\'让小的给您介绍一下！', '订阅欢迎语', '用户订阅之后主动发送的一条欢迎语消息。');
        $form->addInput($welcome);
        /** 返回最大结果条数 **/
        $imageDefault = new Typecho_Widget_Helper_Form_Element_Text('imageDefault', NULL, 'https://holmesian.org/usr/themes/Holmesian/image/avatar.jpg', _t('默认显示图片'), '图片链接，支持JPG、PNG格式，推荐图为80*80。');
        $form->addInput($imageDefault);
        /** 返回最大结果条数 **/
        $imageNum = new Typecho_Widget_Helper_Form_Element_Text('imageNum', NULL, '5', _t('返回图文数量'), '图文消息数量，限制为10条以内。');
        $imageNum->input->setAttribute('class', 'mini');
        $form->addInput($imageNum);
        /** 日志截取字数 **/
        $subMaxNum = new Typecho_Widget_Helper_Form_Element_Text('subMaxNum', NULL, '200', _t('日志截取字数'), '显示单条日志时，截取日志内容字数。');
        $subMaxNum->input->setAttribute('class', 'mini');
        $form->addInput($subMaxNum);

        /** Token **/
        $token = new Typecho_Widget_Helper_Form_Element_Text('token', NULL, '', _t('令牌(Token)'), '需要与开发模式服务器配置中填写一致。服务器地址(URL)：' . Helper::options()->index . '/wechat');
        $form->addInput($token);

        /** APP_ID **/
        $appid = new Typecho_Widget_Helper_Form_Element_Text('WCH_appid', NULL, NULL,
            _t('APP_ID'), _t('需要管理菜单时填写，与开发模式服务器配置中填写一致。'));

        /** APP_Secret **/
        $form->addInput($appid);
        $appsecret = new Typecho_Widget_Helper_Form_Element_Text('WCH_appsecret', NULL, NULL,
            _t('APP_Secret'), _t('需要管理菜单时填写，与开发模式服务器配置中填写一致。'));
        $form->addInput($appsecret);

    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }
}
