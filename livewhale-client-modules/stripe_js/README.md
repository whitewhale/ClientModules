Stripe.js integration for LiveWhale CMS
====

HOW TO INSTALL:

• Drag this module into /livewhale/client/modules/

• In your group settings, add stripe public/private test and live keys. Whichever is set in the group (test or live) will be used on the form.

• Customize "checkout_icon" in the module to point to the complete https:// path of an icon for the charge popup


HOW TO USE:

1) Create a form in a group that has Stripe credentials

2) Create a "Radio buttons" field and add the "Stripe Price" class

3) In the values, for each line use the format
	Name of Item (Price or Further Description)|100
with a | separating the item from the dollar amount of the cost.


OTHER PER-FORM SETTINGS:

Stripe Popup Title
	Default: matches the Form Name
	Set your own: add a hidden form field with the name "stripe_popup_title" and the value you want

Stripe Popup Button
	Default: "Register"
	Set your own: add a hidden form field with the name "stripe_button_title" and the value you want

Stripe Popup Description + Charge Description
	Default: matches the "Name of Item" with values in parentheses stripped out


NOTES:

• If you use "0" as the price for one field, a credit card won't be required for those submissions

• The first price radio button will be selected by default, but you can also pre-select another using the default LiveWhale URL structure: https://docs.livewhale.com/content-types/forms.html#Pre-populating-form-fields-from-the-URL

• If the form validation fails (i.e. missing a required field), the credit card will not be charged