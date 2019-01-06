<?php
/**
 * WeChatHelper Plugin
 *
 * @license    GNU General Public License 2.0
 * 
 */

class WeChatHelper_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $db;

    private $_textTpl;
    private $_imageTpl;
    private $_itemTpl;
    private $_imageNum;
    
    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);

        $this->db = Typecho_Db::get();
        $this->_imageNum = Helper::options()->plugin('WeChatHelper')->imageNum;
        $this->_tulingApi = Helper::options()->plugin('WeChatHelper')->tulingApi;

        $this->_imageDefault = Helper::options()->plugin('WeChatHelper')->imageDefault;
        $this->_textTpl = "<xml>
                            <ToUserName><![CDATA[%s]]></ToUserName>
                            <FromUserName><![CDATA[%s]]></FromUserName>
                            <CreateTime>%s</CreateTime>
                            <MsgType><![CDATA[text]]></MsgType>
                            <Content><![CDATA[%s]]></Content>
                            <FuncFlag>0</FuncFlag>
                            </xml>"; 
        $this->_imageTpl = "<xml>
                             <ToUserName><![CDATA[%s]]></ToUserName>
                             <FromUserName><![CDATA[%s]]></FromUserName>
                             <CreateTime>%s</CreateTime>
                             <MsgType><![CDATA[news]]></MsgType>
                             <ArticleCount>%s</ArticleCount>
                             <Articles>%s</Articles>
                             <FuncFlag>1</FuncFlag>
                             </xml>";
        $this->_itemTpl = "<item>
                             <Title><![CDATA[%s]]></Title> 
                             <Description><![CDATA[%s]]></Description>
                             <PicUrl><![CDATA[%s]]></PicUrl>
                             <Url><![CDATA[%s]]></Url>
                             </item>";
    }

    /**
     * 链接重定向
     * 
     */
    public function link()
    {
        if($this->request->isGet()){
            $this->getAction();
        }
        if($this->request->isPost()){
            $this->postAction();
        }
    }

    /**
     * 校验
     * 
     */
    public function getAction(){
        $_token = Helper::options()->plugin('WeChatHelper')->token;
        $echoStr = $this->request->get('echostr');

        if($this->checkSignature($_token)){
            echo $echoStr;
            exit;
        }
    }

    /**
     * 数据
     * 
     */
    public function postAction(){
        $postStr = file_get_contents("php://input");
	//$this->request->get("HTTP_RAW_POST_DATA");


	//$dir = __TYPECHO_ROOT_DIR__ . __TYPECHO_PLUGIN_DIR__ . '/WeChatHelper';
        //$myfile = $dir.'/wechatDebug.txt';
        //$file_pointer = @fopen($myfile,"a");
        //@fwrite($file_pointer,$postStr );
        //@fclose($file_pointer);


        if (!empty($postStr)){
                $options = Helper::options()->plugin('WeChatHelper');
                $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
                $fromUsername = $postObj->FromUserName;
                $toUsername = $postObj->ToUserName;
                $time = time();
                $msgType = $postObj->MsgType;
                $keyword = trim($postObj->Content);
                $cmd = strtolower(substr($keyword, 0, 1));
                if($msgType == "text" ){
                    if($cmd=="h" || $cmd=="H"){
                        $contentStr = "\"n\" 最新日志\n\"r\" 随机日志\n\"l\" 手气不错\n\"s 关键词\" 搜索日志\n\" ";
                        $resultStr = $this->baseText($postObj, $contentStr);
                    }elseif ($cmd=="f" || $cmd=="F") {
                        $resultStr = $this->commentRank($postObj);
                    }elseif ($cmd=="r" || $cmd=="R") {
                        $resultStr = $this->randomPost($postObj);
                    }elseif ($cmd=="n" || $cmd=="N") {
                        $resultStr = $this->newPost($postObj);
                    }elseif ($cmd=="l" || $cmd=="L") {
                        $resultStr = $this->luckyPost($postObj);
                    }elseif ($cmd=="s" || $cmd=="S") {
                        $searchParam = substr($keyword, 1);
                        $resultStr = $this->searchPost($postObj, $searchParam);
                    }else{
                    	$info = $keyword ;
                        //$resultStr = $this->chatText($postObj, $info);
			$resultStr = $this->searchPost($postObj, $searchParam);
                    }
                }else if($msgType == "event"){
                    if($postObj->Event == "subscribe"){
                        $contentStr = $options->welcome;
                        $resultStr = $this->baseText($postObj, $contentStr);
			//$resultStr = $this->newPost($postObj);
                    }
		    if($postObj->Event == "CLICK"){
			$eventkey = trim($postObj->EventKey);
			if ($eventkey=="n" || $eventkey=="N") {
			$resultStr = $this->newPost($postObj);
			}
			if ($eventkey=="l" || $eventkey=="L") {
			$resultStr = $this->luckyPost($postObj);
                        }

                        if ($eventkey=="r" || $eventkey=="R") {
			$resultStr = $this->randomPost($postObj);
                        }


                    }

                }
                if(empty($resultStr)){
                    $resultStr = $this->baseText($postObj);
                }
                echo $resultStr;
        }else {
            echo "";
            exit;
        }
/*
        $dir = __TYPECHO_ROOT_DIR__ . DIRECTORY_SEPARATOR;
        $myfile = $dir.'nihao.txt';
        echo $myfile;
        $file_pointer = @fopen($myfile,"a");
        @fwrite($file_pointer, $resultStr);
        @fclose($file_pointer);
*/
    }

    public function action(){
        //$this->widget('Widget_User')->pass('administrator');
        //$this->response->goBack();
	$this->link();
	if($this->request->is('menus')){  //菜单业务
            Typecho_Widget::widget('WeChatHelper_Widget_Menus')->action();
        }

    }

    private function checkSignature($_token)
    {
        $signature = $this->request->get('signature');
        $timestamp = $this->request->get('timestamp');
        $nonce = $this->request->get('nonce');
                
        $token = $_token;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        
        if($tmpStr == $signature){
            return true;
        }else{
            return false;
        }
    }
    /** 基础文本信息 **/
    private function baseText($postObj, $contentStr=''){
        if(empty($contentStr)){
            $options = Helper::options()->plugin('WeChatHelper');
            $contentStr =  '<<<你在说什么? 可以发送\'h\'来查看帮助！';
        }
        $fromUsername = $postObj->FromUserName;
        $toUsername = $postObj->ToUserName;
        $time = time();
        $resultStr = sprintf($this->_textTpl, $fromUsername, $toUsername, $time, $contentStr);
        return $resultStr;
    }

    /** 最新日志 **/
    private function newPost($postObj){
        $db = Typecho_Db::get();
        $sql = $db->select()->from('table.contents')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', 'post')
            ->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->limit($this->_imageNum);
        $result = $db->fetchAll($sql);

        $resultStr = $this->sqlData($postObj, $result);
        return $resultStr;
    }

    /** 随机日志 **/
    private function randomPost($postObj){
        $db = Typecho_Db::get();
        $sql = $db->select()->from('table.contents')
            ->where('table.contents.status = ?','publish')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.created <= unix_timestamp(now())', 'post') //添加这一句避免未达到时间的文章提前曝光
            ->limit($this->_imageNum)
            ->order('RAND()');
        $result = $db->fetchAll($sql);

        $resultStr = $this->sqlData($postObj, $result);
        return $resultStr;
    }
    /** 手气不错 **/
    private function luckyPost($postObj){
        $db = Typecho_Db::get();
        $sql = $db->select()->from('table.contents')
            ->where('table.contents.status = ?','publish')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.password IS NULL')
            ->limit(1)
            ->order('RAND()');
        $result = $db->fetchAll($sql);

        $resultStr = $this->sqlData($postObj, $result);
        return $resultStr;
    }

    /** 搜索日志 **/
    private function searchPost($postObj, $searchParam){
        $searchParam = '%' . str_replace(' ', '%', $searchParam) . '%';

        $db = Typecho_Db::get();
        $sql = $db->select()->from('table.contents')
            ->where('table.contents.password IS NULL')
            ->where('table.contents.title LIKE ? OR table.contents.text LIKE ?', $searchParam, $searchParam)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', 'post')
            ->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->limit($this->_imageNum);
        $result = $db->fetchAll($sql);

        $resultStr = $this->sqlData($postObj, $result);
        return $resultStr;
    }

    private function sqlData($postObj, $data){
        $_subMaxNum = Helper::options()->plugin('WeChatHelper')->subMaxNum;
        $resultStr = "";
        $num = 0;
        $tmpPicUrl = "";

        if($data != null){
        	foreach($data as $val){
                $val = Typecho_Widget::widget('Widget_Abstract_Contents')->filter($val);
                $content = Typecho_Common::subStr(strip_tags($val['text']), 0, $_subMaxNum, '...');
                //$preg = "/<img\ssrc=(\'|\")(.*?)\.(jpg|png)(\'|\")/is";
                $preg = "/\[.*?\]:\s*(http(s)?:\/\/.*?(jpg|png))/i"; //For markdown first pic.
				preg_match_all( $preg, $val['text'], $matches );
				if(isset($matches) && isset($matches[1][0])){
				 	$tmpPicUrl = $matches[1][0];
				}else{
					$preg = '/<img\ssrc=(\'|\")(.*?)\.(jpg|png)(\'|\")/is';//default src pic
					preg_match_all( $preg, $val['text'], $matches );
					if(isset($matches) && isset($matches[1][0])){
                                 	        $tmpPicUrl = $matches[1][0];
                                	}else{
						$preg = '/\!\[.*?\]\((http(s)?:\/\/.*?(jpg|png))/i';
						preg_match_all( $preg, $val['text'], $matches );
						if(isset($matches) && isset($matches[1][0])){
                                          	      $tmpPicUrl = $matches[1][0];
                                        	}else{
							$tmpPicUrl = $this->_imageDefault;
						}
					}
					
					//$tmpPicUrl = $this->_imageDefault;
				}
				$tmpPicUrl = $tmpPicUrl."?imageView2/1/w/300";
                $resultStr .= sprintf($this->_itemTpl, $val['title'], $content, $tmpPicUrl, $val['permalink']);
                $num++;
            }
        }else{
                $resultStr = "没有找到任何信息！";
        }
        $fromUsername = $postObj->FromUserName;
        $toUsername = $postObj->ToUserName;
        $time = time();
        if($data != null){
            $resultStr = sprintf($this->_imageTpl, $fromUsername, $toUsername, $time, $num, $resultStr);
        }else{
            $resultStr = sprintf($this->_textTpl, $fromUsername, $toUsername, $time, $resultStr);
        }
        return $resultStr;
    }

}
?>
