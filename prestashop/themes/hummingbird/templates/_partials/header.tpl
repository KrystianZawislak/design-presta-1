{**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *}

{$headerBanner = 'header-banner'}
{$headerTop = 'header-top'}
{$headerBottom = 'header-bottom'}
{$headerNavFullWidth = 'header-nav-full-width'}

{capture name="header_banner"}{hook h='displayBanner'}{/capture}
{block name='header_banner'}
  {if !empty($smarty.capture.header_banner)}
    <div class="{$headerBanner}">
      {$smarty.capture.header_banner nofilter}
    </div>
  {/if}
{/block}

{capture name="header_nav_1"}{hook h='displayNav1'}{/capture}
{capture name="header_nav_2"}{hook h='displayNav2'}{/capture}
{capture name="header_free_shipping_bar"}{hook h='displayFreeShippingBar'}{/capture}
{block name='header_nav'}
  {if !empty($smarty.capture.header_nav_1) || !empty($smarty.capture.header_nav_2) || !empty($smarty.capture.header_free_shipping_bar)}
    <div class="{$headerTop} d-none d-md-block">
      <div class="container-md">
        <div class="row">
          <div class="{$headerTop}__left col-md-4">
            {if !empty($smarty.capture.header_free_shipping_bar)}
              <div class="{$headerTop}__free-shipping-bar row">
                {$smarty.capture.header_free_shipping_bar nofilter}
              </div>
            {/if}
            <a href="{$link->getCMSLink(1)}">{l s='Delivery' d='Shop.Theme.Global'}</a>
            <a href="{$link->getCMSLink(5)}">{l s='Secure payment' d='Shop.Theme.Global'}</a>
          </div>

          <div class="{$headerTop}__right col-md-8">
            {$smarty.capture.header_nav_1 nofilter}
            <a href="{$link->getCMSLink(4)}">{l s='About us' d='Shop.Theme.Global'}</a>
            {$smarty.capture.header_nav_2 nofilter}
          </div>
        </div>
      </div>
    </div>
  {/if}
{/block}

{block name='header_bottom'}
  <div class="{$headerBottom}">
    <div class="{$headerBottom}__container container-md">
      <div class="{$headerBottom}__row row gx-2 gx-md-4 align-items-stretch">
        <div class="header-master">
          <div class="{$headerBottom}__logo d-flex align-items-center col-auto me-auto me-md-0">
            {if $shop.logo_details}
              {if $page.page_name == 'index'}<h1 class="{$headerBottom}__h1 mb-0">{/if}
                {renderLogo}
              {if $page.page_name == 'index'}</h1>{/if}
            {/if}
          </div>

          {capture name="header_mega_menu_switcher"}{hook h='displayMegaMenuSwitcher'}{/capture}
          {if !empty($smarty.capture.header_mega_menu_switcher)}
            <div class="header-master__switcher">
              {$smarty.capture.header_mega_menu_switcher nofilter}
            </div>
          {/if}

          {hook h='displayTop' excl='ps_mainmenu'}

          <div id="_mobile_ps_customersignin" class="d-md-none d-flex col-auto">
            {* JUST PLACEHOLDER FOR RESPONSIVE COMPONENT TO LOAD REAL ONE *}
            <div class="header-block">
              <a href="{$urls.pages.my_account}" class="header-block__action-btn">
                <svg class="header-block__icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="8" r="4"></circle>
                  <path d="M4 20a8 8 0 0 1 16 0"></path>
                </svg>
              </a>
            </div>
            {* JUST PLACEHOLDER FOR RESPONSIVE COMPONENT TO LOAD REAL ONE *}
          </div>

          {if !$configuration.is_catalog}
            <div id="_mobile_ps_shoppingcart" class="d-md-none d-flex col-auto">
              {* JUST PLACEHOLDER FOR RESPONSIVE COMPONENT TO LOAD REAL ONE *}
              <div class="header-block">
                <a href="{$urls.pages.cart}" class="header-block__action-btn">
                  <span class="header-block__icon-wrap">
                    <svg class="header-block__icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="9" cy="20" r="1.5"></circle>
                      <circle cx="18" cy="20" r="1.5"></circle>
                      <path d="M2.5 3h2l2.4 12.1a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 8H6"></path>
                    </svg>
                    <span class="header-block__badge">{$cart.products_count}</span>
                  </span>
                </a>
              </div>
              {* JUST PLACEHOLDER FOR RESPONSIVE COMPONENT TO LOAD REAL ONE *}
            </div>
          {/if}

          {if $customer.is_logged}
            <div id="_mobile_blockwishlist" class="d-md-none d-flex col-auto">
              <div class="header-block">
                <a href="{$link->getModuleLink('blockwishlist', 'lists')}" class="header-block__action-btn">
                  <svg class="header-block__icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"></path>
                  </svg>
                </a>
              </div>
            </div>
          {/if}
        </div>

        <div class="header-main_menu">
          {hook h='displayTop' mod='ps_mainmenu'}
        </div>
      </div>
    </div>
  </div>

  {capture name="nav_full_width"}{hook h='displayNavFullWidth'}{/capture}
  {if !empty($smarty.capture.nav_full_width)}
    <div class="{$headerNavFullWidth}">
      {$smarty.capture.nav_full_width nofilter}
    </div>
  {/if}
{/block}
