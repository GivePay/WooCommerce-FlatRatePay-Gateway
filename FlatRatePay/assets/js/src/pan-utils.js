import $ from 'jquery';
import _ from "lodash";

/**
 * GivePay PAN Utils
 *
 * Utility plugin for PAN validation in JQuery
 */


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
    let check = 0;
    let bEven = false;

    value = value.replace(/\D/g, "");

    for (let n = value.length - 1; n >= 0; n--) {
        let cDigit = value.charAt(n);
        let nDigit = parseInt(cDigit, 10);

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