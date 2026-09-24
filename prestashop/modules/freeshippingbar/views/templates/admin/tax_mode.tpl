{foreach $languages as $language}
  <div class="form-group translatable-field lang-{$language.id_lang}"{if $language.id_lang != $default_form_language} style="display:none;"{/if}>
    <div class="col-lg-10">
      <select name="{$field_name}_{$language.id_lang}" class="form-control">
        <option value="brutto"{if $tax_mode_values[$language.id_lang] == 'brutto'} selected="selected"{/if}>{$brutto_label}</option>
        <option value="netto"{if $tax_mode_values[$language.id_lang] == 'netto'} selected="selected"{/if}>{$netto_label}</option>
      </select>
    </div>
    <div class="col-lg-2">
      <button type="button" class="btn btn-default dropdown-toggle" tabindex="-1" data-toggle="dropdown">
        {$language.iso_code}
        <span class="caret"></span>
      </button>
      <ul class="dropdown-menu">
        {foreach $languages as $switch_language}
          <li><a href="javascript:hideOtherLanguage({$switch_language.id_lang});" tabindex="-1">{$switch_language.name}</a></li>
        {/foreach}
      </ul>
    </div>
  </div>
{/foreach}
