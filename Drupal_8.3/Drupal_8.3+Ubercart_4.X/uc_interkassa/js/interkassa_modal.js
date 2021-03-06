/**
 * @file
 * Handles asynchronous requests for order editing forms.
 */

jQuery(function ($) {
  Drupal.behaviors.uc_interkassa = {
      attach: function (context, settings) {
          $('body',context).prepend('<div class="blLoaderIK"><div class="loaderIK"></div></div>');
          $('.radioBtn a', context).on('click', function () {
              
              $('.blLoaderIK').css('display', 'block');
              var form = $('#uc-payment-interksassa-offsite-form');
              var sel = $(this).data('title');
              var tog = $(this).data('toggle');
               curtrigger = true;
                var ik_cur = this.innerText;
                console.log(ik_cur);
                var ik_pw_via = $(this).attr('data-title');

                if($('input[name =  "ik_pw_via"]').length > 0){
                    $('input[name =  "ik_pw_via"]').val(ik_pw_via);
                }else{
                    form.append(
                        $('<input>', {
                            type: 'hidden',
                            name: 'ik_pw_via',
                            val: ik_pw_via
                        }));
                }

              $('#' + tog).prop('value', sel);
              $('a[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('notActive');
              $('a[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('notActive').addClass('active');
 
              var payment_metod = $(this).attr('data-payment');
              $('input[name ="payment_metod"]').val(payment_metod);
              $('.blLoaderIK').css('display', 'none');
          });
          $('.ik-payment-confirmation').click(function (e) {
              e.preventDefault();
              $('.blLoaderIK').css('display', 'block');

              var payment_metod = $('input[name ="payment_metod"]').val();
              var ik_pw_via = $('input[name ="ik_pw_via"]').val();
              var form = $('#uc-payment-interksassa-offsite-form');
              var ik_pm_no = $('input[name ="ik_pm_no"]').val();
 
              if (payment_metod != $(this).attr('data-payment') || ik_pw_via == '') {
                  alert('Вы не выбрали валюту');
                  return;
              }
              $('.blLoaderIK').css('display', 'block');
              console.log('ok0.1');
              if (ik_pw_via.search('test_interkassa_test_xts|qiwi|rbk') == -1) {
                        console.log('ok');

                  form.append(
                      $('<input>', {
                          type: 'hidden',
                          name: 'ik_act',
                          val: 'process'
                      }));
                  form.append(
                      $('<input>', {
                          type: 'hidden',
                          name: 'ik_int',
                          val: 'json'
                      }));
                  $.post(form.attr('action'), form.serialize(), function (data) {
                         console.log(data);
                          paystart(data);
                      })
                      .fail(function () {
                          alert('Something wrong');
                      })

              }
              else {
                  
                  $('input[name="ik_act"]').remove();
                  $('input[name="ik_int"]').remove();
                  var url = $('form.interkass-payment-modal-form').attr('action');
                  
                  form.submit();
                  
              }
              $('.blLoaderIK').css('display', 'none');
          });
          function paystart(data) {
              data_array = IsJsonString(data) ? JSON.parse(data) : data
              var form = $('#uc-payment-interksassa-offsite-form');
              if (data_array['resultCode'] != 0) {
                  $('input[name="ik_act"]').remove();
                  $('input[name="ik_int"]').remove();
                  var url = $('form.interkass-payment-modal-form').attr('action');
                  $.post(url, form.serialize(), function (data) {
                      $('input[name ="ik_sign"]').val(data);
                  })
                  form.submit();
              }
              else {
                  if (data_array['resultData']['paymentForm'] != undefined) {
                      var data_send_form = [];
                      var data_send_inputs = [];
                      data_send_form['url'] = data_array['resultData']['paymentForm']['action'];
                      data_send_form['method'] = data_array['resultData']['paymentForm']['method'];
                      for (var i in data_array['resultData']['paymentForm']['parameters']) {
                          data_send_inputs[i] = data_array['resultData']['paymentForm']['parameters'][i];
                      }
                      $('body').append('<form method="' + data_send_form['method'] + '" id="tempformIK" action="' + data_send_form['url'] + '"></form>');
                      for (var i in data_send_inputs) {
                          $('#tempformIK').append('<input type="hidden" name="' + i + '" value="' + data_send_inputs[i] + '" />');
                      }
                      $('#tempformIK').submit();
                  }
                  else {
                      $('.ui-icon-closethick').trigger('click');
                      if (document.getElementById('tempdivIK') == null)
                          form.after('<div id="tempdivIK">' + data_array['resultData']['internalForm'] + '</div>');
                      else
                          $('#tempdivIK').html(data_array['resultData']['internalForm']);
                  }
              }
          }

          function IsJsonString(str) {
              try {
                  JSON.parse(str);
              } catch (e) {
                  return false;
              }
              return true;
          }
      }
  }
});