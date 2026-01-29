
jQuery(function($){
  $('#seojusai-analyze-now').on('click',function(e){
    e.preventDefault();
    var post=$(this).data('post');
    $.post(ajaxurl,{action:'seojusai_analyze_now',post_id:post},function(){
      alert('Аналіз додано в чергу');
    });
  });
});
