let GivePayGateway = function() {

    let _createClient = function (mid, token, url) {
        return _gateway(mid, token, url);
    };

    return {
        createClient: _createClient
    };

}();

let _gateway = function(mid, token, url) {
    let _preValidateRequest = function () {
        return token !== '' & mid !== '';
    };

    let _createRequestBody = function(pan, expMonth, expYear) {
        let req = {
            terminal: {
                tid: 'not used',
                entry_method: 'com.givepay.transactions.entry-method.keypad'
            },
            card: {
                card_number: pan,
                expiration_month: expMonth.toString().padStart(2, '0'),
                expiration_year: expYear.toString().padStart(2, '0')
            },
            mid: mid
        };

        return JSON.stringify(req);
    };

    let _getTokenizationUrl = function () {
        if (typeof url === 'undefined') {
            return 'https://gateway.givepaycommerce.com/api/v1/transactions/tokenize';
        }

        return url;
    };

    let _getToken = function (pan, expMonth, expoYear) {
        let $dfd = jQuery.Deferred();

        if (!_preValidateRequest()) {
            return '';
        }

        jQuery.ajax(_getTokenizationUrl(), {
            contentType: 'application/json; charset=utf-8',
            data: _createRequestBody(pan, expMonth, expoYear),
            dataType: 'json',
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token
            }
        }).done(function (data) {
            if (data.error != null) {
                $dfd.reject(data.error);
            }

            if (data.result.result_code !== 0) {
                $dfd.reject();
                return;
            }

            $dfd.resolve(data.result.token);
        }).fail(function () {
            $dfd.reject();
        });

        return $dfd.promise();
    };

    return {
        getToken: _getToken
    };
};