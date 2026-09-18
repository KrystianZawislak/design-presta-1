{**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *}

<div id="_desktop_ps_shoppingcart" class="order-4">
  <div class="ps-shoppingcart">
    <div class="header-block d-flex align-items-center blockcart cart-preview {if $cart.products_count> 0}header-block--active{else}inactive{/if}" data-refresh-url="{$refresh_url}">
      <a class="header-block__action-btn{if !$customer.is_logged} pe-md-0{/if}" rel="nofollow" href="{$cart_url}" aria-label="{l s='View cart (%d products)' d='Shop.Theme.Checkout' sprintf=[$cart.products_count]}">

      <span class="header-block__icon-wrap">
        <svg class="header-block__icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="9" cy="20" r="1.5"></circle>
          <circle cx="18" cy="20" r="1.5"></circle>
          <path d="M2.5 3h2l2.4 12.1a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 8H6"></path>
        </svg>
        <span class="header-block__badge">{$cart.products_count}</span>
      </span>
      <span class="d-none d-md-flex header-block__title">{l s='Cart' d='Shop.Theme.Checkout'}</span>

      </a>
    </div>
  </div>
</div>
