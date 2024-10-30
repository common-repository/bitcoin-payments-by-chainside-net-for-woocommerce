=== Chainside Bitcoin Payments ===
Contributors: chainside
Tags: bitcoin, payment, payment gateway, ecommerce, accept bitcoin, bitcoin payments, bitcoin woocommerce, bitcoin wordpress plugin
Requires at least: 5.3.0
Tested up to: 5.3.2
Requires PHP: 5.6
Stable tag: 1.0.0
License:
License URI:
Attributions:


== Description ==
Chainside is a payment processor for Bitcoin payments, providing software to make the integration of a merchant with the Bitcoin network as simple as possible. Chainside provides therefore a simple interface abstracting all the complexity of Bitcoin and providing an integration experience similar to traditional payment methods.

The Chainside Bitcoin Payment extension is designed to help WooCommerce merchants to accept Bitcoin payments in their store.
By accepting Bitcoin payments you provide an extra option to your customers and get access to a large pool of users you may not have access to traditional financial infrastructure.

When you add the extension to your store, a "Pay with Bitcoin" option will appear close to the other payment methods. If the Bitcoin payment option is chosen, the customer will be redirected to a Chainside checkout page where all the information needed to complete the payment (bitcoin address and bitcoin amount) will be shown. As soon as the payment is received, the customer is redirected to your WooCommerce store and your back-end will be notified.

To use the extension, you  first have to register (for free) on the Chainside's platform at business.chainside.net, where you will be able to create an account and link it to you personal Bitcoin wallet. From you Chainside account, you can then create an object called webPOS which will be in charge of generating a payment order and communicate with your WooCommerce store. From the webPOS settings you will also be able to choose what is the source of the of dynamic exchange rate you wish to use, and how many confirmation of the Bitcoin network you wish to wait before a payment is considered as finalised.


== Installation ==

Please note, of this gateway requires WooCommerce 2.6 and above.


= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of the WooCommerce Chainside plugin, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “WooCommerce Chainside Payment Gateway” and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating, and description. Most importantly, of course, you can install it by simply clicking "Install Now", then "Activate".

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.


