<?php

class AssetAdminExport extends LeftAndMainExtension
{

    private static $allowed_actions = array(
        'backup'
    );

    /**
     * @param Form $form
     * @return Form $form
     */
    public function updateEditForm(Form $form)
    {
        $backupButton = new LiteralField(
            'BackupButton',
            sprintf(
                '<a class="ss-ui-button ss-ui-action ui-button-text-icon-primary" data-icon="arrow-circle-135-left" title="%s" href="%s">%s</a>',
                'Performs an asset backup in ZIP format. Useful if you want all assets and have no FTP access',
                $this->owner->Link('backup'),
                'Backup files'
            )
        );

        if ($field = $this->fieldByExtraClass($form->Fields(), 'cms-actions-row')) {
            $field->push($backupButton);
        }

        return $form;
    }

    /**
     * Recursively search & return a field by 'extra class' from FieldList.
     * 
     * @todo Could be added as a FieldList extension but it's a bit overkill for the sake of a button
     * 
     * @param FieldList $fields 
     * @param $class The extra class name to search for
     * @return FormField|null
     */
    public function fieldByExtraClass(FieldList $fields, $class)
    {
        foreach ($fields as $field) {
            if ($field->extraClasses && in_array($class, $field->extraClasses)) {
                return $field;
            }
            if ($field->isComposite()) {
                return $this->fieldByExtraClass($field->FieldList(), $class);
            }
        }
    }

    /**
     * @return SS_HTTPRequest
     */
    public function backup()
    {
        $name = 'assets_' . SS_DateTime::now()->Format('Y-m-d') . '.zip';
        $tmpName = TEMP_FOLDER . '/' . $name;
        $zip = new ZipArchive();

        if (!$zip->open($tmpName, ZipArchive::OVERWRITE)) {
            user_error('Asset Export Extension: Unable to read/write temporary zip archive', E_USER_ERROR);
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                ASSETS_PATH,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($files as $file) {
            $local = str_replace(ASSETS_PATH . '/', '', $file);
            $zip->addFile($file, $local);
        }

        if (!$zip->status == ZipArchive::ER_OK) {
            user_error('Asset Export Extension: ZipArchive returned an error other than OK', E_USER_ERROR);
            return;
        }

        $zip->close();
        
        if (ob_get_length()) {
            @ob_flush();
            @flush();
            @ob_end_flush();
        }
        @ob_start();

        $content = file_get_contents($tmpName);
        unlink($tmpName);

        return SS_HTTPRequest::send_file($content, $name);
    }
}
