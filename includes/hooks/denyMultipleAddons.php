<?php

use WHMCS\Service\Addon;
use WHMCS\Database\Capsule;

/**
 * WHMCS Deny Multiple Addons Hook
 *
 * A helper that prevents more than one of each addon from being purchased per product (e.g. service)
 *
 * @package    WHMCS
 * @author     Lee Mahoney <lee@leemahoney.dev>
 * @copyright  Copyright (c) Lee Mahoney 2022
 * @license    MIT License
 * @version    1.0.2
 * @link       https://leemahoney.dev
 */

if (!defined('WHMCS')) {
    die('You cannot access this file directly.');
}

function deny_multiple_addons($vars) {

    # Which statuses should be used to identify that the addon can no longer be added.
    $statuses = [
        'Active',
        'Suspended',
        'Pending',
        'Completed',
        'Fraud'
    ];

    # Only run the following block of code on the "View Available Addons" page.
    if ($_SERVER['REQUEST_URI'] == '/cart.php?gid=addons') {

        # Grab the addons currently being outputted on the page
        $theAddons  = $vars['addons'];

        # Loop through the addons to allow us intercept and alter them
        foreach ($theAddons as $key => $theAddon) {

            # Since an addon can have multiple products associated with it, let's focus on them instead of the addon overall
            foreach ($theAddon['productids'] as $id => $product) {
                

                # Check the database for the current addon in question, that relates to the current user and relevant product (from the dropdown).
                # Check also if the status is one of those listed in the $statuses array
                $check = Addon::where([
                    'hostingid' => $product['id'],
                    'addonid'   => $theAddon['id'], 
                    'userid'    => $_SESSION['uid']
                ])->whereIn('status', $statuses)->count();

                # If the result is not zero, it means there is an active addon for that product, lets remove it from the array we've created (and will output later)
                if ($check != 0) {
                    unset($theAddons[$key]['productids'][$id]);
                }

                # We also don't want the client to be able to purchase more than one quantity of the addon, even if its not active in the database. E.g. they add it to their cart, go back and add it again. Defeats the purpose.
                # Loop through the current addons in the session and compare, if one matches the current addon and productid then remove it also from the dropdown list
                foreach ($_SESSION['cart']['addons'] as $addon) {
                    if ($addon['id'] == $theAddon['id'] && $addon['productid'] == $product['id']) {
                        unset($theAddons[$key]['productids'][$id]);
                    }
                }
    
                

            }
            
            # Rather than show an empty dropdown list if no products are available to add the addon to, lets remove it entirely
            if (count($theAddons[$key]['productids']) == 0) {
                unset($theAddons[$key]);
            }

        }

        # Overwrite the original $addons array in the smarty template with our customized one
        return ['addons' => $theAddons];

    }

    # This code runs on all pages, basically if the user somehow manages to add an addon for the second time, this will only make sure that one instance of the addon exists in the customers cart per product
    $addonsArray = [];
    
    # Loop through the addons in the users session
    foreach ($_SESSION['cart']['addons'] as $key => $addon) {

        $userCheck = false;

        # If the user is logged in and somehow the same addon got activated before they managed to complete the cart, add it for removal
        if (isset($_SESSION['uid'])) {
            $userCheck = Addon::where([
                'hostingid' => $addon['productid'],
                'addonid'        => $addon['id'], 
                'userid'    => $_SESSION['uid']
            ])->whereIn('status', $statuses)->count();
        }

        # If the addon matches our array by id and product id, remove the addon's array from the users session (and redirect so it doesn't still show two in the cart until the user refreshes)
        # Same goes for if the user check above returns a result
        if($addonsArray[$addon['id']][$addon['productid']] == 1 || $userCheck != 0) {
            unset($_SESSION['cart']['addons'][$key]);
            header("Location: {$_SERVER['REQUEST_URI']}");
        } else {
            # Otherwise add to the array so if the client does manage to add a second addon matching the same id and product id, it will fail on the next run
            $addonsArray[$addon['id']][$addon['productid']] = 1;
        }

    }

}

# Add the hook
add_hook('ClientAreaPageCart', 1, 'deny_multiple_addons');
