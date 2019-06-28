import jQuery from 'jquery';
import * as pad from './padStart';
import * as panUtils from './pan-utils';
import GivePayGateway from './gateway';



(function($) {
    let _$checkoutForm = $('form[name="checkout"]');
    let _client = GivePayGateway(gpgMid, gpgAccessToken, gpgUrl);

    const tokenName = 'givepay_gateway-gpg-token';
    const panName = 'givepay_gateway-card-number-last4';


    let addTokenToForm = function ($form, token) {
      let $input = $form.find('input[name="' + tokenName +'"]');
      if ($input.length > 0) {
          $input.val(token);
      } else {
          let html = '<input type="hidden" id="' + tokenName + '" name="' + tokenName + '" value="' + token + '">';
          $form.append(html);
      }
    };

    let addPanLast4ToForm = function ($form, pan) {
        let last4 = pan.substr(-4);
        let $input = $form.find('input[name="' + panName +'"]');
        if ($input.length > 0) {
            $input.val(last4);
        } else {
            let html = '<input type="hidden" id="' + panName + '" name="' + panName + '" value="' + last4 + '">';
            $form.append(html);
        }
    };

    _$checkoutForm.onValidPan(function (pan, expMonth, expYear) {
        _client.getToken(pan, expMonth, expYear).then(token => {
            addTokenToForm(_$checkoutForm, token);
            addPanLast4ToForm(_$checkoutForm, pan);
        });
    }, '#givepay_gateway-card-number', '#givepay_gateway-card-expiry');

}(jQuery));