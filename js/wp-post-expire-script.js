jQuery(document).ready(function($){
  $('.datetimepicker').datetimepicker({
   alwaysSetTime: false,
   dateFormat: 'd MM yy',
   timeFormat: 'HH:mm',
  });
  $('.datepicker').datepicker({
    dateFormat: 'd MM yy',
  });
  $('.timepicker').timepicker({
    alwaysSetTime: false,
    timeFormat: 'HH:mm',
  });

});
