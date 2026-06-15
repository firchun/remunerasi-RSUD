<?php
class ErrorController extends BaseController
{
    public function notFound()
    {
        http_response_code(404);
        $this->renderRaw('errors/404');
    }

    public function serverError($message = 'Internal Server Error', $file = '', $line = 0)
    {
        http_response_code(500);
        $data = [
            'message' => $message,
            'file'    => $file,
            'line'    => $line,
        ];
        $this->renderRaw('errors/500', $data);
    }
}
