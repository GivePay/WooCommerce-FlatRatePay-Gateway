import jQuery from 'jquery';
import _ from 'lodash';
import GivePayGateway from './gateway.js';

/**
 * GivePay PAN Utils
 *
 * Utility plugin for PAN validation in JQuery
 */
;(function($) {

    // https://gist.github.com/DiegoSalazar/4075533
    // Takes the form field value and returns true on valid number
    function valid_credit_card(value) {
        // accept only digits, dashes or spaces
        if (/[^0-9-\s]+/.test(value)) {
            return false;
        }

        // Only PANs of a valid length
        if (value.length < 12) {
            return false;
        }

        // The Luhn Algorithm. It's so pretty.
        var check = 0;
        var bEven = false;

        value = value.replace(/\D/g, "");

        for (var n = value.length - 1; n >= 0; n--) {
            var cDigit = value.charAt(n);
            var nDigit = parseInt(cDigit, 10);

            if (bEven) {
                if ((nDigit *= 2) > 9) nDigit -= 9;
            }

            check += nDigit;
            bEven = !bEven;
        }

        return (check % 10) === 0;
    }

    /**
     * Calls the callback handler when the value of the target is a
     * valid PAN.
     *
     * @param cb callback
     * @param panSelector the selector for the pan input
     * @param expSelector the selector for the exp date input
     **/
    $.fn.onValidPan = function(cb, panSelector, expSelector) {

        let evaluate = function () {
            let pan = $(panSelector).val().replace(/[^0-9]/gi, '');
            let expMonth = undefined;
            let expYear = undefined;

            if (typeof expSelector !== 'undefined') {
                let components = $(expSelector).val().split('/');
                if (components.length !== 2) {
                    return;
                }

                let year = parseInt(components[1]);
                let month = parseInt(components[0]);
                if (isNaN(year + month)) {
                    return;
                }

                expYear = year;
                expMonth = month;
            }

            if (valid_credit_card(pan)) {
                cb(pan, expMonth, expYear);
            }
        };

        let _cb = _.debounce(evaluate, 500);

        $(this).on('input propertychange paste', panSelector, _cb);

        if (typeof expSelector !== 'undefined') {
            $(this).on('input propertychange paste', expSelector, _cb);
        }
    };
}(jQuery));

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