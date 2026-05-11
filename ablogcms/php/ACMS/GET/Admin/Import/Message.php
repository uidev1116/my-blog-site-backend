<?php

class ACMS_GET_Admin_Import_Message extends ACMS_GET
{
    public function get()
    {
        if (!sessionWithAdministration()) {
            die403();
        }
        $tpl = new Template($this->tpl, new ACMS_Corrector());

        $importMessage = $this->Post->get('importMessage');
        $successFlag = $this->Post->get('success');
        $errorCount = (int) $this->Post->get('importErrorCount');
        $errorsJson = $this->Post->get('importErrors');
        $blogName = $this->Post->get('blogName');
        $categoryName = $this->Post->get('categoryName');
        $entryCount = (int) $this->Post->get('entryCount');
        $errors = json_decode($errorsJson !== '' ? $errorsJson : '[]', true);
        if (!is_array($errors)) {
            $errors = [];
        }

        $errors = array_map(function ($e) {
            return [
                'index' => isset($e['index']) ? (int) $e['index'] : 0,
                'message' => isset($e['message']) ? (string) $e['message'] : '',
            ];
        }, $errors);

        $data = [
            'errors' => $errors,
            'importMessage' => $importMessage,
            'success' => $successFlag,
            'errorCount' => $errorCount,
            'blogName' => $blogName,
            'categoryName' => $categoryName,
            'entryCount' => $entryCount,
        ];

        if ($successFlag === 'on') {
            $data['import'] = [
                'blogName' => $blogName,
                'categoryName' => $categoryName,
                'entryCount' => $entryCount,
                'errorCount' => $errorCount,
            ];
        }

        return $tpl->render($data);
    }
}
