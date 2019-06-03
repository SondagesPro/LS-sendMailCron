/**
 * @file Part of sendMailCron plugin
 * @author Denis Chenu
 * @copyright Denis Chenu <http://www.sondages.pro>
 * @license magnet:?xt=urn:btih:d3d9a9a6595521f9666a5e94cc830dab83b65699&dn=expat.txt Expat (MIT)
 */
$("[data-moveto='surveybarid']").appendTo("#surveybarid > .row >.col-md-12");
$(document).on('click',"[data-click-name='savesendMailCron']",function(e){
  e.preventDefault();
  $("button[name='savesendMailCron'][value='"+$(this).data('click-value')+"']").trigger("click");
});
