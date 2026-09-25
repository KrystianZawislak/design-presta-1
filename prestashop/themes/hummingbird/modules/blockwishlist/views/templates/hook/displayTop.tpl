{**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *}

<div id="_desktop_blockwishlist" class="order-2">
  <div class="ps-blockwishlist">
    <div class="header-block d-flex align-items-center {if $wishlistProductsCount > 0}header-block--active{else}inactive{/if}">
      <a
        class="header-block__action-btn"
        rel="nofollow"
        href="{$url}"
        aria-label="{l s='View wishlist (%d products)' sprintf=[$wishlistProductsCount] d='Modules.Blockwishlist.Shop'}"
      >
        <span class="header-block__icon-wrap">
          <svg class="header-block__icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"></path>
          </svg>
          {if $wishlistProductsCount > 0}
            <span class="header-block__badge">{$wishlistProductsCount}</span>
          {/if}
        </span>
        <span class="d-none d-lg-flex header-block__title">{l s='Wishlist' d='Modules.Blockwishlist.Shop'}</span>
      </a>
    </div>
  </div>
</div>
