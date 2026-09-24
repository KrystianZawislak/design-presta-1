{**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *}

<div id="_desktop_ps_customersignin" class="order-3">
  <div class="ps-customersignin">
    {if $customer.is_logged}
      <div class="dropdown header-block">
        <button
          class="header-block__action-btn border-0 bg-transparent"
          id="userMenuButton"
          data-bs-toggle="dropdown"
          aria-haspopup="true"
          aria-expanded="false"
          aria-label="{l s='View my account (%customerName%)' sprintf=['%customerName%' => $customerName] d='Shop.Theme.Customeraccount'}"
        >
          <svg class="header-block__icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"></circle>
            <path d="M4 20a8 8 0 0 1 16 0"></path>
          </svg>
          <span class="header-block__title d-lg-inline d-none">
            {$customerName|capitalize|truncate:22:"...":true}
          </span>
        </button>

        <div class="dropdown-menu dropdown-menu-start" aria-labelledby="userMenuButton">
          <a
            href="{$urls.pages.my_account}"
            class="dropdown-item"
            rel="nofollow"
            {if $urls.current_url == $urls.pages.my_account}aria-current="page"{/if}
          >
            <i class="material-icons me-2" aria-hidden="true">&#xF02E;</i>
            {l s='Your account' d='Shop.Theme.Customeraccount'}
          </a>

          <div class="dropdown-divider"></div>

          <a
            href="{$urls.pages.identity}"
            class="dropdown-item"
            rel="nofollow"
            {if $urls.current_url == $urls.pages.identity}aria-current="page"{/if}
          >
            <i class="material-icons me-2" aria-hidden="true">&#xE853;</i>
            {l s='Information' d='Shop.Theme.Customeraccount'}
          </a>

          {if $customer.addresses|count}
            <a
              href="{$urls.pages.addresses}"
              class="dropdown-item"
              rel="nofollow"
              {if $urls.current_url == $urls.pages.addresses}aria-current="page"{/if}
            >
              <i class="material-icons me-2" aria-hidden="true">&#xF00F;</i>
              {l s='Addresses' d='Shop.Theme.Customeraccount'}
            </a>
          {else}
            <a
              href="{$urls.pages.address}"
              class="dropdown-item"
              rel="nofollow"
              {if $urls.current_url == $urls.pages.address}aria-current="page"{/if}
            >
              <i class="material-icons me-2" aria-hidden="true">&#xEF3A;</i>
              {l s='Add first address' d='Shop.Theme.Customeraccount'}
            </a>
          {/if}

          {if !$configuration.is_catalog}
            <a
              href="{$urls.pages.history}"
              class="dropdown-item"
              rel="nofollow"
              {if $urls.current_url == $urls.pages.history}aria-current="page"{/if}
            >
              <i class="material-icons me-2" aria-hidden="true">&#xE916;</i>
              {l s='Orders' d='Shop.Theme.Customeraccount'}
            </a>
          {/if}

          {if !$configuration.is_catalog}
            <a
              href="{$urls.pages.order_slip}"
              class="dropdown-item"
              rel="nofollow"
              {if $urls.current_url == $urls.pages.order_slip}aria-current="page"{/if}
            >
              <i class="material-icons me-2" aria-hidden="true">&#xE8B0;</i>
              {l s='Credit slips' d='Shop.Theme.Customeraccount'}
            </a>
          {/if}

          {if $configuration.voucher_enabled && !$configuration.is_catalog}
            <a
              href="{$urls.pages.discount}"
              class="dropdown-item"
              rel="nofollow"
              {if $urls.current_url == $urls.pages.discount}aria-current="page"{/if}
            >
              <i class="material-icons me-2" aria-hidden="true">&#xE54E;</i>
              {l s='Vouchers' d='Shop.Theme.Customeraccount'}
            </a>
          {/if}

          {if $configuration.return_enabled && !$configuration.is_catalog}
            <a
              href="{$urls.pages.order_follow}"
              class="dropdown-item"
              rel="nofollow"
              {if $urls.current_url == $urls.pages.order_follow}aria-current="page"{/if}
            >
              <i class="material-icons me-2" aria-hidden="true">&#xE860;</i>
              {l s='Merchandise returns' d='Shop.Theme.Customeraccount'}
            </a>
          {/if}

          <div class="dropdown-divider"></div>

          <a 
            href="{$logout_url}"
            class="dropdown-item"
            rel="nofollow"
          >
            <i class="material-icons me-2" aria-hidden="true">&#xE879;</i>
            {l s='Sign out' d='Shop.Theme.Actions'}
          </a>
        </div>
      </div>
    {else}
      <div class="header-block">
        <a
          href="{$urls.pages.authentication}?back={$urls.current_url|urlencode}"
          class="header-block__action-btn"
          rel="nofollow"
          aria-label="{l s='Sign in' d='Shop.Theme.Actions'}"
        >
          <svg class="header-block__icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"></circle>
            <path d="M4 20a8 8 0 0 1 16 0"></path>
          </svg>
          <span class="d-none d-lg-inline header-block__title">
            {l s='Sign in' d='Shop.Theme.Actions'}
          </span>
        </a>
      </div>
    {/if}
  </div>
</div>
