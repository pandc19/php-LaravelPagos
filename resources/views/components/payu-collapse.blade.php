<label class="mt-3">Card details:</label>

<div class="form-group form-row">
    <div class="col-4">
        <input type="text" class="form-control" name="payu_card" placeholder="Card Number">
    </div>

    <div class="col-2">
        <input type="text" class="form-control" name="payu_cvc" placeholder="CVC">
    </div>

    <div class="col-1">
        <input type="text" class="form-control" name="payu_month" placeholder="MM">
    </div>

    <div class="col-1">
        <input type="text" class="form-control" name="payu_year" placeholder="YY">
    </div>

    <div class="col- 2">
        <select class="custom-select" name="payu_network">
            <option selected>Select</option>
            <option value="visa">VISA</option>
            <option value="amex">AMEX</option>
            <option value="diners">DINERS</option>
            <option value="mastercard">MASTERCARD</option>
        </select>
    </div>

</div>

<div class="form-group form-row">
    <div class="col-5">
        <input type="text" class="form-control" name="payu_name" placeholder="Your Name">
    </div>
    <div class="col-5">
        <input type="email" class="form-control" placeholder="email@example.com" name="payu_email">
    </div>
</div>

<div class="form-group form-row">
    <div class="col">
        <small class="form-text text-mute" role="alert">Your payment will be converted to {{strtoupper(config('services.payu.base_currency'))}}
        </small>
    </div>
</div>
