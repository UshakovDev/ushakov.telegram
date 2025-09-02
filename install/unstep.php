<?php if(!check_bitrix_sessid()) return; global $APPLICATION; ?>
<form method="post" action="<?=$APPLICATION->GetCurPage();?>">
    <?=bitrix_sessid_post();?>
    <p>
        <label>
            <input type="checkbox" name="USH_TG_REMOVE_DATA" value="Y">
            <?=GetMessage("USH_TG_REMOVE_DATA");?>
        </label>
    </p>
    <input type="hidden" name="lang" value="<?=LANGUAGE_ID?>">
    <input type="hidden" name="id" value="ushakov.telegram">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    <input type="submit" value="<?=GetMessage("USH_TG_UNINSTALL_BTN");?>" class="adm-btn-save">
</form>
