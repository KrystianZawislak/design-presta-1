{* PrestaShop license placeholder *}

{* GENERATE DESKTOP MEGA PANEL (columns, all visible at once, links stacked vertically) *}
{function name="desktopMegaPanel" item=[]}
  {if $item.columns|count}
    <div class="js-sub-menu submenu" role="menu" aria-label="{l s='%s submenu' sprintf=[$item.label] d='Shop.Theme.Menu'}" id="submenu-{$item.page_identifier}" data-ps-ref="desktop-submenu">
      <div class="container">
        <div class="submenu__row row">
          <div class="submenu__right col-12" data-ps-ref="desktop-submenu-right">
            <div class="submenu__right-items megamenu-columns" style="grid-template-columns: repeat({$item.columns|count}, 1fr);">
              {foreach from=$item.columns item=column}
                <ul class="ps-mainmenu__group--{($column.links|count) ? 'child' : 'nochild'} megamenu-column">
                  <li class="megamenu-column__title">{$column.title}</li>
                  {if $column.see_all}
                    <li>
                      <a class="ps-mainmenu__group-main-item megamenu-column__see-all" href="{$column.see_all.url}">
                        {$column.see_all.label}
                      </a>
                    </li>
                  {/if}
                  {foreach from=$column.links item=link}
                    <li>
                      <a class="megamenu-column__link" href="{$link.url}" {if $link.open_in_new_window}target="_blank"{/if}>
                        {$link.label}
                      </a>
                    </li>
                  {/foreach}
                </ul>
              {/foreach}
            </div>
          </div>
        </div>
      </div>
    </div>
  {/if}
{/function}

{* GENERATE DESKTOP FIRST LEVEL *}
{function name="desktopFirstLevel" itemsFirstLevel=[]}
  {if $itemsFirstLevel|count}
    <ul class="ps-mainmenu__tree" id="top-menu" data-ps-ref="desktop-menu-tree">
      {foreach from=$itemsFirstLevel item=menuItem}
        <li class="ps-mainmenu__tree-item type-{$menuItem.type} {if $menuItem.current} current{/if}" data-id="{$menuItem.page_identifier}" data-ps-ref="desktop-menu-item">
          <div class="ps-mainmenu__tree-item-wrapper">
            <a
              class="ps-mainmenu__tree-link"
              href="{$menuItem.url}"
              data-depth="1"
              data-ps-ref="desktop-menu-link"
              {if $menuItem.current}aria-current="page"{/if}
              {if $menuItem.open_in_new_window}target="_blank" rel="noopener noreferrer"{/if}
            >
              {$menuItem.label}
            </a>
            {if $menuItem.has_dropdown && $menuItem.columns|count}
              <button
                class="ps-mainmenu__tree-dropdown-toggle dropdown-toggle"
                type="button"
                aria-haspopup="menu"
                aria-expanded="false"
                aria-controls="submenu-{$menuItem.page_identifier}"
                aria-label="{l s='Open %s submenu' sprintf=[$menuItem.label] d='Shop.Theme.Menu'}"
                data-ps-ref="desktop-menu-dropdown-toggle"
              ></button>
            {/if}
          </div>

          {if $menuItem.has_dropdown}
            {desktopMegaPanel item=$menuItem}
          {/if}
        </li>
      {/foreach}
    </ul>
  {/if}
{/function}

{* GENERATE DESKTOP MENU *}
{function name="desktopMenu" nodes=[]}
  {desktopFirstLevel itemsFirstLevel=$nodes}
{/function}

{* GENERATE MOBILE MENU *}
{function name="mobileMenu" nodes=[]}
  <nav class="menu menu--mobile menu--current js-menu-current" id="menu-mobile" data-depth="0">
    <ul class="menu__list">
      {foreach from=$nodes item=menuItem}
        <li class="type-{$menuItem.type} {if $menuItem.current} current{/if}" id="{$menuItem.page_identifier}">
          <a class="menu__link" href="{$menuItem.url}" {if $menuItem.open_in_new_window}target="_blank"{/if}>
            {$menuItem.label}
          </a>
        </li>
        {foreach from=$menuItem.columns item=column}
          <li class="menu__title">{$column.title}</li>
          {if $column.see_all}
            <li>
              <a class="menu__link" href="{$column.see_all.url}">{$column.see_all.label}</a>
            </li>
          {/if}
          {foreach from=$column.links item=link}
            <li>
              <a class="menu__link" href="{$link.url}" {if $link.open_in_new_window}target="_blank"{/if}>{$link.label}</a>
            </li>
          {/foreach}
        {/foreach}
      {/foreach}
    </ul>
  </nav>
{/function}

<div class="ps-mainmenu ps-mainmenu--desktop col-xl col-auto">
  {* DESKTOP MENU *}
  <nav class="ps-mainmenu__desktop d-none d-xl-block position-static js-menu-desktop" data-ps-ref="desktop-menu-container" aria-label="{l s='Main navigation' d='Shop.Theme.Menu'}">
    {desktopMenu nodes=$menu.children}
  </nav>

  {* MOBILE MENU *}
  <div class="ps-mainmenu__mobile-toggle">
    <button
      class="menu-toggle btn btn-link"
      data-bs-toggle="offcanvas"
      data-bs-target="#mobileMenu"
      aria-controls="mobileMenu"
      aria-label="{l s='Open mobile menu' d='Shop.Theme.Menu'}"
    >
      <span class="material-icons" aria-hidden="true">&#xE5D2;</span>
    </button>
  </div>
</div>

<div
  class="ps-mainmenu ps-mainmenu--mobile offcanvas offcanvas-start js-menu-canvas"
  tabindex="-1"
  id="mobileMenu"
  aria-labelledby="mobileMenuLabel"
>
  <div class="offcanvas-header">
    <div class="ps-mainmenu__back-button">
      <button class="btn btn-link btn-sm d-none js-back-button" type="button" aria-label="{l s='Go back to main menu' d='Shop.Theme.Menu'}">
        <span class="material-icons rtl-flip" aria-hidden="true">&#xE5CB;</span>
        <span class="js-menu-back-title">{l s='All' d='Shop.Theme.Global'}</span>
      </button>
    </div>
    <button type="button" class="btn-close btn text-reset" data-bs-dismiss="offcanvas" aria-label="{l s='Close' d='Shop.Theme.Global'}"></button>
  </div>

  <div class="ps-mainmenu__mobile">
    {mobileMenu nodes=$menu.children}
  </div>

  <div class="ps-mainmenu__additionnals offcanvas-body d-flex flex-wrap align-items-center gap-3">
    <div class="ps-mainmenu__selects d-flex gap-2 me-auto">
      <div id="_mobile_ps_currencyselector" class="col-auto"></div>
      <div id="_mobile_ps_languageselector" class="col-auto"></div>
    </div>
    <div id="_mobile_ps_contactinfo"></div>
  </div>
</div>
