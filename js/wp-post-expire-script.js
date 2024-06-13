/**
 * WP Post Expire JS
 * @author Archie M
 * 
 * Version: 1.0.1
 * 
 */

jQuery(document).ready(function($) {
  
  'use strict';

  var dateFormat = 'd MM yy';
  var timeFormat = 'HH:mm';

  if ($('.datetimepicker').length) {
      $('.datetimepicker').datetimepicker({
          alwaysSetTime: false,
          dateFormat: dateFormat,
          timeFormat: timeFormat
      });
  }

  if ($('.datepicker').length) {
      $('.datepicker').datepicker({
          dateFormat: dateFormat
      });
  }

  if ($('.timepicker').length) {
      $('.timepicker').timepicker({
          alwaysSetTime: false,
          timeFormat: timeFormat
      });
  }

});
