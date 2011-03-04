create table pscoupon_coupons (
  id int not null auto_increment,
  code varchar(255),
  shop_order_id int,
  shop_coupon_id int,
  shop_order_status_id int,
  used tinyint default 0,
  delete_on_use tinyint default 0,
  primary key(`id`),
  index code_index (`code`(5))
);