{* GENERATE MOBILE MENU (root screen + one drill-down child screen per item with columns) *}
{function name="mobileMenu" nodes=[]}
  <nav class="menu menu--mobile menu--current" id="menu-mobile" data-depth="0">
    <ul class="menu__list">
      {foreach from=$nodes item=menuItem}
        <li class="type-{$menuItem.type}{if $menuItem.current} current{/if}" id="{$menuItem.page_identifier}">
          {if $menuItem.has_dropdown && $menuItem.columns|count}
            <div class="menu--childrens">
              <a class="menu__link" href="{$menuItem.url}" {if $menuItem.open_in_new_window}target="_blank"{/if}>
                {$menuItem.label}
              </a>
              <button
                type="button"
                class="menu__toggle-child js-mobile-menu-open"
                data-target="{$menuItem.page_identifier}"
                aria-label="{l s='Open %s submenu' sprintf=[$menuItem.label] d='Shop.Theme.Menu'}"
              >
                <span class="material-icons" aria-hidden="true">&#xE5CC;</span>
              </button>
            </div>
          {else}
            <a class="menu__link" href="{$menuItem.url}" {if $menuItem.open_in_new_window}target="_blank"{/if}>
              {$menuItem.label}
            </a>
          {/if}
        </li>
      {/foreach}
    </ul>
  </nav>

  {foreach from=$nodes item=menuItem}
    {if $menuItem.has_dropdown && $menuItem.columns|count}
      <nav class="menu" data-id="{$menuItem.page_identifier}" data-depth="1" data-back-title="{$menuItem.label}">
        <ul class="menu__list">
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
        </ul>
      </nav>
    {/if}
  {/foreach}
{/function}

{mobileMenu nodes=$menu.children}
