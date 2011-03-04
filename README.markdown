NOTE
====
This module is to help programmers add coupon aliases in LemonStand.  In its current incarnation it is of no use to an end user without a developer to integrate it.

Known issues
==========
* Adds duplicates of the shop module's actions to the action dropdown for Pages.  I'm hoping to either find a fix or to wait until events/hooks are published to allow cleaner access at coupon codes.

Installation
------------

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
        
Usage
-----

The core of this module is provided by the following function:

    PSCoupon_Module::create_coupon(
      $code_or_length = null, 
      $key = null, 
      $shop_coupon_id = null, 
      $order_status = 'paid', 
      $delete_on_use = false
    )
    
It accepts the following parameters:

* `code_or_length` => either a coupon alias you wish to use, or the length of an alphanumerical code to randomly generate
* `key` => for added randomness when generating, if you have something unique to this customer (email address, for example) pass it here
* `shop_coupon_id` => the id, code, or object of the Shop_Coupon object this aliases
* `order_status` => the id, code, or object of the Shop_OrderStatus at which point this coupon should be marked as _used_.  If you wish to mark the coupon as used as soon as the order is created, pass in numerical 0 (not a string).
* `delete_on_use` => should the database record be purged once this coupon is used, or leave it for other purposes?

The method returns an stdClass object with the following properties:

* `id` => self explanatory
* `code` => the alias code
* `shop_order_id` => id of the Shop_Order object this coupon is assigned to
* `shop_coupon_id` => id of the Shop_Coupon object this coupon aliases
* `shop_order_status_id` => id of the Shop_OrderStatus object that indicate when this alias should be marked as used
* `used` => boolean (0/1) indicating whether or not this alias has been used
* `delete_on_use` => boolean (0/1) indicating whether this alias should be deleted when it's marked as used
