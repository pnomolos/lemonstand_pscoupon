NOTE
====
This module is to help programmers add coupon aliases in LemonStand.  In its current incarnation it is of no use to an end user without a developer to integrate it.

After installing the module, you'll need to alter your cart_partial partial (or equivalent) and add some code on your Cart page (or equivalent).

* In your cart_partial, the checkout button should send a request to 'PSCoupon:on_setCouponCode' rather than 'shop:on_setCouponCode'.
* In your Cart page, add the following Pre Action code

        if ( filter_input( INPUT_POST, 'coupon' ) !== NULL ) {
          Cms_VisitorPreferences::set('coupon', post('coupon')); 
        }
        $coupon_code = Cms_VisitorPreferences::get('coupon', post('coupon'));
        $_POST['coupon'] = PSCoupon_Module::filter_coupon_code($coupon_code);​

3. In your Cart page, add the following Post Action code

        $this->data['coupon_code'] = PSCoupon_Module::reverse_filter_coupon_code($this->data['coupon_code']);
    