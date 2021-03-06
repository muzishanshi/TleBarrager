<?php
/**
 * 为Typecho增加评论弹幕功能<div class="barragerSet"><br /><a href="javascript:;" title="插件因兴趣于闲暇时间所写，故会有代码不规范、不专业和bug的情况，但完美主义促使代码还说得过去，如有bug或使用问题进行反馈即可。">鼠标轻触查看备注</a>&nbsp;<a href="http://club.tongleer.com" target="_blank">论坛</a>&nbsp;<a href="https://www.tongleer.com/api/web/pay.png" target="_blank">打赏</a>&nbsp;<a href="http://mail.qq.com/cgi-bin/qm_share?t=qm_mailme&email=diamond0422@qq.com" target="_blank">反馈</a></div><style>.barragerSet a{background: #4DABFF;padding: 5px;color: #fff;}</style>
 * @package TleBarragerForTypecho弹幕插件
 * @author 二呆
 * @version 1.0.3<br /><span id="barragerUpdateInfo"></span><script>barragerXmlHttp=new XMLHttpRequest();barragerXmlHttp.open("GET","https://www.tongleer.com/api/interface/TleBarrager.php?action=update&version=3",true);barragerXmlHttp.send(null);barragerXmlHttp.onreadystatechange=function () {if (barragerXmlHttp.readyState ==4 && barragerXmlHttp.status ==200){document.getElementById("barragerUpdateInfo").innerHTML=barragerXmlHttp.responseText;}}</script>
 * @link http://www.tongleer.com/
 * @date 2019-10-17
 */
define('TLEBARRAGER_VERSION', '3');
class TleBarrager_Plugin implements Typecho_Plugin_Interface{
    // 激活插件
    public static function activate(){
		Typecho_Plugin::factory('Widget_Archive')->header = array('TleBarrager_Plugin', 'header');
        return _t('插件已经激活');
    }

    // 禁用插件
    public static function deactivate(){
        return _t('插件已被禁用');
    }

    // 插件配置面板
    public static function config(Typecho_Widget_Helper_Form $form){
		$options = Typecho_Widget::widget('Widget_Options');
		$plug_url = $options->pluginUrl;
		
		$div = new Typecho_Widget_Helper_Layout();
		$html='
			<b>使用方法：</b><br />
			<small>
			1、根据需求决定是否填写指定文章ID；<br />
			2、为防止jquery冲突，如果主题中加载了jquery，以下需要选择“否”；<br />
			3、需要手动到主题目录的post.php中，添加以下代码即可。<font color="blue">（如果网站开启了pjax，需要在pjax容器内添加以下代码，可搜索$(document).pjax其中的第二个参数即为pjax容器的css选择器类名，再在添加以下代码时注意添加位置即可。）</font><br />
			<font color="red">&lt;?php TleBarrager_Plugin::show($this);?></font>
			</small>
		';
		$div->html($html);
		$div->render();
		
		$isEnableJQuery = new Typecho_Widget_Helper_Form_Element_Radio('isEnableJQuery', array(
            'y'=>_t('是'),
            'n'=>_t('否')
        ), 'y', _t('是否加载JQuery'), _t("用于解决jquery冲突问题，如果主题head中自带jquery，需要选择否；如果主题中未加载jquery，则需要选择是。"));
		$form->addInput($isEnableJQuery->addRule('enum', _t(''), array('y', 'n')));
		
		$ArticleId = new Typecho_Widget_Helper_Form_Element_Text('ArticleId', NULL, NULL, _t('指定文章ID'), _t('指定文章ID可指定弹幕显示的ID，多个请用英文逗号隔开，默认为空或0即为全部。'));
        $form->addInput($ArticleId);
    }

    // 个人用户配置面板
    public static function personalConfig(Typecho_Widget_Helper_Form $form){
    }
	
	// 获得插件配置信息
    public static function getConfig(){
        return Typecho_Widget::widget('Widget_Options')->plugin('TleBarrager');
    }
	
	public static function show($widget){
		$cid = $widget->cid;
		$option=self::getConfig();
		$ArticleIds=$option->ArticleId;
		$ArticleIdArr = explode(',', $ArticleIds);
        $ArticleIdArr = array_unique($ArticleIdArr);
		if($ArticleIds!=null&&$ArticleIds!=0&&!in_array($cid,$ArticleIdArr)) {
            return;
        }
		$db = Typecho_Db::get();
		$sql = "SELECT * FROM ".$db->getPrefix()."comments where cid={$cid} order by cid desc";
		$result = $db->fetchAll($sql);
		$arr_put = array();
		foreach($result as $row){
			$gravatar = self::gravatar($row["mail"]);
			$content = mb_strimwidth($row["text"],0, 58,'...');
			$content = htmlspecialchars($content);
			$content = self::commentEmoji($content);
			$a = array(
			'info'   => "$content",
			'img'    => "$gravatar",
			'close'  => "resource/images/close.png",
			);
			$arr_put[]=$a;
		}
		if(!empty($arr_put)){
			$barrager = json_encode($arr_put);
		}
		if(!empty($barrager)&&strpos($_SERVER["QUERY_STRING"],"WeiboFile")===false){
			echo '<script>
				var data = '.$barrager.';
				var items=data;
				/*弹幕总数*/
				var total=data.length;
				var looper;
				/*每条弹幕发送间隔*/
				var looper_time=3*1000;
				total>20?looper_time=800:looper_time=3*1000;
				/*是否首次执行*/
				var run_once=true;
				var clear = false;
				/*弹幕索引*/
				var index=0;
				var ixof = false;
				/*先执行一次*/
				/*barrager();*/
				function barrager(){
				  if(run_once){
					  /*如果是首次执行,则设置一个定时器,并且把首次执行置为false*/
					  looper=setInterval(barrager,looper_time);                
					  run_once=false;
				  }
				  /*发布一个弹幕*/
				  $("body").barrager(items[index]);
				  /*索引自增*/
				  index++;
				  /*所有弹幕发布完毕，清除计时器。*/
				  if(clear){clearInterval(looper);return false;}
				  if(index > total){
					  index = 0;
					  barrager_close();
				  }
				}
				function barrager_close(){
					clear = true;
					ixof = false;
					clearInterval(looper);
					$.fn.barrager.removeAll();
				}
				function barrager_start(){
					if(ixof){return false;}
					/*是否首次执行*/
					 run_once=true;
					 clear = false;
					/*先执行一次*/
					 ixof =true;
					barrager();
				}
				barrager_start();
				document.addEventListener("visibilitychange", function() {
					if(document.hidden){
						barrager_close();
					}else{
						barrager_start();
					}
				});
				</script>
			';
		}
	}
	
	public static function gravatar($email, $s = 40, $d = 'mm', $g = 'g') {
		$ssud=explode("@",$email,2);
		if(@$ssud[1]=='qq.com'){
			return "http://q.qlogo.cn/headimg_dl?bs=qq&dst_uin={$ssud[0]}&src_uin=qq.feixue.me&fid=blog&spec=100";
		}else{
			$hash = md5($email);
			$avatar = "http://cn.gravatar.com/avatar/$hash?s=$s&d=$d&r=$g";
			return $avatar;
		}
	}
	
	/**
	 * @des emoji 标签处理评论并输出
	 * @param $str 评论数据
	 * @return string
	 */
	public static function commentEmoji($str) {
		$options = Typecho_Widget::widget('Widget_Options');
		$plug_url = $options->pluginUrl;
		$data = array(
			array('url' => 'images/face/1.png','title' =>  "微笑") ,
			array('url' => 'images/face/5.png','title' => "得意" ) ,
			array('url' => 'images/face/6.png','title' =>"愤怒") ,
			array('url' => 'images/face/7.png','title' => "调戏" ) ,
			array('url' => 'images/face/9.png','title' => "大哭" ) ,
			array('url' => 'images/face/10.png','title' =>"汗"  ) ,
			array('url' => 'images/face/11.png','title' => "鄙视" ) ,
			array('url' => 'images/face/13.png','title' =>  "真棒") ,
			array('url' => 'images/face/14.png','title' => "金钱" ) ,
			array('url' => 'images/face/16.png','title' => "瞧不起" ) ,
			array('url' => 'images/face/19.png','title' =>  "委屈") ,
			array('url' => 'images/face/21.png','title' =>"惊讶") ,
			array('url' => 'images/face/24.png','title' =>"可爱") ,
			array('url' => 'images/face/25.png','title' => "滑稽" ) ,
			array('url' => 'images/face/26.png','title' => "调皮") ,
			array('url' => 'images/face/27.png','title' => "大汉") ,
			array('url' => 'images/face/28.png','title' =>"可怜") ,
			array('url' => 'images/face/29.png','title' => "睡觉" ) ,
			array('url' => 'images/face/30.png','title' => "流泪" ) ,
			array('url' => 'images/face/31.png','title' => "气出泪" ) ,
			array('url' => 'images/face/33.png','title' =>"喷") ,
			array('url' => 'images/face/39.png','title' => "月亮")  ,
			array('url' => 'images/face/40.png','title' => "太阳")  ,
			array('url' => 'images/face/43.png','title' => "咖啡")  ,
			array('url' => 'images/face/44.png','title' => "蛋糕")  ,
			array('url' => 'images/face/45.png','title' => "音乐")  ,
			array('url' => 'images/face/47.png','title' => "yes")  ,
			array('url' => 'images/face/48.png','title' => "大拇指")  ,
			array('url' => 'images/face/49.png','title' => "鄙视你"),
			array('url' => 'images/face/50.png','title' => "程序猿")
		);
		foreach($data as $key=>$value) {
			$str = str_replace('['.$value['title'].']','<img class="comment_face" src="'.$plug_url."/TleBarrager/resource/".$value['url'].'">',$str);
		}
		return $str;
	}
	
	public static function header(){
		$option=self::getConfig();
		if($option->isEnableJQuery=="y"){
			echo '<script src=https://apps.bdimg.com/libs/jquery/1.7.1/jquery.min.js></script>';
		}
		$jsUrl = Helper::options()->pluginUrl."/TleBarrager/resource/js/jquery.barrager.js";
		echo '<script src="'.$jsUrl.'"></script>';
		$cssUrl = Helper::options()->pluginUrl . '/TleBarrager/resource/css/barrager.css';
		echo '<link rel="stylesheet" href="'.$cssUrl.'"  media="all">';
	}
}