<?
$index = array(
    0 => array()
);

function getUser($commentArr) {
    $select = User::model();
    $criteria = $select->getDbCriteria();
    $criteria->limit = 1;
    $criteria->order = 'RANDOM()';

    return $select->find();
}

foreach($comments as &$item) {
    $parentId = isset($item['parentCommentId']) ? $item['parentCommentId'] : 0;

    if(!isset($index[$parentId])) {
        $index[$parentId] = array();
    }

    $index[$parentId][$item['id']] = &$item;
    $item['user'] = $this->getUserForRComment($item);
}

$app = Yii::App();
$user = $app->user;

?>

<script type="text/javascript">
//<![CDATA[
    var commentForm;
    $(document).ready(function () {
        $(this).commentForm({
            urlFrame: 'http://66.ru/new66_upload_gate.php'
        });
        commentForm = $('#comment-form');
        commentForm.find('form').attr('action', '');
        commentForm.find('.js_comment_form').css('display', 'block');
        commentForm.find('.js_comment_form').append('<input type="hidden" name="comment[parentCommentId]" value="0" />');
        commentForm.data('parentCommentId', commentForm.find('input[name="comment[parentCommentId]"]'));
        commentForm.show();

        $('.js_comment-to-comment').click(function(){
            var re = new RegExp('[0-9]+');
            var tmp = $(this).attr('id').match(re);

            if(!tmp)
                return;

            var commentId = tmp[0];
            commentForm.data('parentCommentId').val(commentId)
            commentForm.appendTo($(this).parent().next());
            return false;
        });
    });
//]]>
</script>



<!--стандартный блок комментариев с новостей 66-->
<div id="comments-<?php echo $this->objectTypeCode;?>-<?php echo $this->objectId;?>" class="js_comments context">
    <div class="b-sep"></div>

    <?php if(!$user->isGuest){?>
    <div id="comment-form" style="display: none;">  <?php //скрыта, потому что сначала, пока страница не загрузилась - выглядит страшно!! ?>
    <form action="" method="post">
        <fieldset>
            <textarea id="comments_textarea" name="comment[content]" rows="10"></textarea>
        </fieldset>
    </form>
    </div>
    <?php }?>

    <?function printComments(&$index, &$comments, $key = 0){?>
        <? if(!isset($index[$key])) return; ?>
        <?foreach($index[$key] as $item){?>
            <div id="c<?php echo $item['id'];?>" class="js_comment  ie_layout" comremoved="0" style="display: block;">
              <div class="comment_head rc5">
               <div class="comment_head_avatar"><img alt="" src="<?php echo $item['user']->getAvatar();?>"></div>
                   <a href="<?php echo $item['user']->getProfileLink();?>" class="js_user js_user-1 js_user-f-off "><?php echo $item['user']->getUsername();?></a>
                   <i class="comment_head_date"><?php echo DateHelper::formatRusDate($item['createTime']);?></i>
                   <?/*<a href="default.php#" title="Игнорировать сообщения этого пользовтаеля" class="buttons_report_small comment_head_icon">Игнорировать сообщения этого пользователя</a>
                   <form method="post" class="inline-block comment_head_icon js_comment_remove" action="/comments/commentAjax/deleteComment/">
                         <input type="hidden" value="123731" name="commentId" class="forms_hidden">
                         <input type="submit" value="Удалить" title="Удалить комментарий" name="delete" class="forms_submit  buttons_remove_small comment_head_buttons_remove_small">
                   </form>*/?>
                   <a title="Ссылка на комментарий" href="#c<?php echo $item['id'];?>" class="buttons_anchor_small comment_head_icon">Ссылка</a>
                   <?/*<a style="display: none;" class="show_bad_comment" href="default.php#"><i></i>показать комментарий</a>*/?>
                   <?/*<a href="default.php#" class="comment_head_next-new">следующий новый<span class="comment_head_next-new-pic"></span></a>*/?>
               </div>
               <div class="comment_content content"><?php echo $item['contentParsed'];?></div>
               <div class="comment_foot context">
                   <a href="#c<?php echo $item['id'];?>" class="comment_foot_answer js_comment-to-comment" id="c<?php echo $item['id'];?>">ответить</a>
                   <div class="hr comment_foot_hr"><hr></div>
               </div>
                <div class="comment-form"></div>
                <div class="js_comment-sublevel_wrap" id="c<?php echo $item['id'];?>s">
                    <?php printComments($index, $comments, $item['id']); ?>
                </div>
            </div>
        <?}?>
    <?}?>

    <?php printComments($index, $comments); ?>

    <? /* <div style="display: block;" class="all-comments-wrap"><span class="all-comments">Все комментарии</span> (27)</div> */?>



</div>