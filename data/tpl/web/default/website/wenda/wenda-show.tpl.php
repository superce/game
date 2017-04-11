<?php defined('IN_IA') or exit('Access Denied');?><?php (!empty($this) && $this instanceof WeModuleSite || 0) ? (include $this->template('common/header-cms', TEMPLATE_INCLUDEPATH)) : (include template('common/header-cms', TEMPLATE_INCLUDEPATH));?>
<style>
/* about */
/* content */
.content{ margin:30px 0;}
.content h5{ font-weight:bold; font-size:16px; line-height:50px;}
.content p{ line-height:26px; color:#666; margin-bottom:10px;}
.about_content{ overflow:hidden;zoom:1; margin-top:20px;}
.about_content .left{ width:200px; float:left; margin-right:20px; border:1px solid #ddd;}
.about_content .left a{ display:block; padding:10px 20px; border-bottom:1px solid #ddd;}
.about_content .right{ float:left; padding:0 20px 20px; width:900px;}
</style>
<link href="./resource/weidongli/css/cms.css" rel="stylesheet" type="text/css" />
<div class="neirong">
<?php  if($do == 'list') { ?>
<div class="breadcrumbs margin-bottom-40 cl">
<div class="xiaoyu_container cl">
        <h1 class="color-green z"><a href="<?php  echo url('website/wenda-show/list');?>">产品问答</a></h1>
        <ul class="y breadcrumb">
        <li><a href="./" title="首页">首页</a> <span class="divider">/</span></li>
		<li><a href="<?php  echo url('website/wenda-show/list');?>">问题列表</a><span class="divider">/</span></li>
		<li class="active"><?php  if(!$cateid) { ?>所有问题<?php  } else { ?><?php  echo $categroys[$cateid]['title'];?><?php  } ?></li>
        </ul>
</div>
</div>
<div class="xiaoyu_container cl">
<div class="Public-content clearfix">
  <div class="Public" id="authing">
    <div class="Public-box1 clearfix" style="padding:0;">
      <div class="reg-wrapper3" style="padding:0;">
        <div class="qa-nav-cat">
          <ul class="qa-nav-cat-list">
            <li class="qa-nav-cat-list-title">按分类查看</li>
            <li <?php  if($_W['page']['title'] == '所有问题') { ?>class="qa-nav-cat-item-selected" <?php  } ?>>
              <a href="<?php  echo url('website/wenda-show/list');?>">
                <span>所有分类</span>
              </a>
            </li>
                <?php  if(is_array($categroys)) { foreach($categroys as $categroy) { ?>	
            <li <?php  if($_W['page']['title'] == $categroy['title']) { ?>class="qa-nav-cat-item-selected" <?php  } ?> >
              <a href="<?php  echo url('website/wenda-show/list', array('cateid' => $categroy['id']));?>">
                <span><?php  echo $categroy['title'];?></span>
              </a>
            </li>
            <?php  } } ?>
          </ul>
        </div>
        <div class="qa-q-list">
          <div class="qa-nav-main">
            <ul class="qa-nav-main-list">
              <li class="qa-nav-main-item qa-nav-main-item-selected qa-nav-main-questions">
                <a class="qa-nav-main-link qa-nav-main-selected" href="">最近问题</a>
              </li>

            </ul>
            <div class="qa-nav-main-clear"></div>
          </div>
            <?php  if(is_array($data)) { foreach($data as $da) { ?>
          <div class="qa-q-list-item">
            <h3>
              <span class="qa-ask">问</span><?php  echo $da['title'];?></h3>
            <p>
              <span class="qa-answer">答</span><?php  echo cutstr_num($da['content'],'298');?>
			  <a href="<?php  echo url('website/wenda-show/detail', array('id' => $da['id']));?>" target="_blank" class="qa-answer2">查看更多 </a>
			  </p>
          </div>
		  <?php  } } ?>
 	<?php  echo $pager;?>
        </div>
      </div>
    </div>
  </div>

</div>
</div>

<?php  } ?>
<?php  if($do == 'detail') { ?>
<div class="breadcrumbs margin-bottom-40 cl">
<div class="xiaoyu_container cl">
        <h1 class="color-green z"><a href="<?php  echo url('website/wenda-show/list');?>">产品问答</a></h1>
        <ul class="y breadcrumb">
        <li><a href="./" title="首页">首页</a> <span class="divider">/</span></li>
		<li><a href="<?php  echo url('website/wenda-show/list');?>">问题列表</a><span class="divider">/</span></li>
		<li class="active"><?php  echo $wenda['title'];?></li>
        </ul>
</div>
</div>
<div class="xiaoyu_container cl">
<div class="Public-content clearfix">
  <div class="Public" id="authing">
    <div class="Public-box1 clearfix" style="padding:0;">
      <div class="reg-wrapper3" style="padding:0;">
        <div class="qa-nav-cat">
          <ul class="qa-nav-cat-list">
            <li class="qa-nav-cat-list-title">按分类查看</li>
            <li <?php  if($_W['page']['title'] == '所有问题') { ?>class="qa-nav-cat-item-selected" <?php  } ?>>
              <a href="<?php  echo url('website/wenda-show/list');?>">
                <span>所有分类</span>
              </a>
            </li>
            <?php  if(is_array($categroys)) { foreach($categroys as $categroy) { ?>	
            <li <?php  if($_W['page']['title'] == $categroy['title']) { ?>class="qa-nav-cat-item-selected" <?php  } ?> >
              <a href="<?php  echo url('website/wenda-show/list', array('cateid' => $categroy['id']));?>">
                <span><?php  echo $categroy['title'];?></span>
              </a>
            </li>
            <?php  } } ?>
          </ul>
        </div>
        <div class="qa-q-list">
          <div class="qa-nav-main">
            <ul class="qa-nav-main-list">
              <li class="qa-nav-main-item qa-nav-main-item-selected qa-nav-main-questions">
                <a class="qa-nav-main-link qa-nav-main-selected" href="">问题详情</a>
              </li>

            </ul>
            <div class="qa-nav-main-clear"></div>
          </div>
          <div class="qa-q-list-item">
            <h3>
              <span class="qa-ask">问</span><?php  echo $wenda['title'];?></h3>
            <p>
              <span class="qa-answer">答</span><?php  echo $wenda['content'];?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
</div>
<?php  } ?>
</div>

<?php (!empty($this) && $this instanceof WeModuleSite || 0) ? (include $this->template('common/footer-cms', TEMPLATE_INCLUDEPATH)) : (include template('common/footer-cms', TEMPLATE_INCLUDEPATH));?>