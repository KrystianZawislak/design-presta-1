<?php

class BlockWishListOverride extends BlockWishList
{
    public function hookDisplayTop(array $params)
    {
        if (false === $this->context->customer->isLogged()) {
            return '';
        }

        $wishlistProducts = WishList::getAllProductByCustomer($this->context->customer->id, $this->context->shop->id);

        $this->smarty->assign([
            'url' => $this->context->link->getModuleLink('blockwishlist', 'lists'),
            'wishlistProductsCount' => $wishlistProducts ? count($wishlistProducts) : 0,
        ]);

        return $this->fetch('module:blockwishlist/views/templates/hook/displayTop.tpl');
    }
}
