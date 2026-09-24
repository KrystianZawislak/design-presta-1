<nav class="megamenu-switcher" data-select-url="{$megaMenuSelectUrl}">
  <ul class="nav">
    {foreach $megaMenuCategories as $category}
      <li class="nav-item">
        <button type="button" class="nav-link{if $category.active} active{/if}" data-id-category="{$category.id_category}">{$category.name}</button>
      </li>
    {/foreach}
  </ul>
</nav>
