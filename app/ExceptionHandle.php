<?php

namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use think\Request;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render(Request $request, Throwable $e): Response
    {
        // 添加自定义异常处理机制

        // C 端开放 API（api 应用）：异常统一渲染为 JSON 包络（恒 HTTP 200）
        // 说明：路由/控制器内抛出的异常由 TP8 Pipeline 在 $next 内渲染后作为普通响应
        // 返回，仍会穿过 CStyleResponse 完成包络转换；本分支兜底的是路由管线之外的
        // 异常（全局中间件阶段、应用级 404、非业务 Throwable 等），按
        // config('nft.c_end_style') 自行完成风格适配。
        if ($this->app->exists('http') && $this->app->http->getName() === 'api') {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }

            $code = \app\common\service\nft\CException::SYSTEM_ERROR;
            $msg  = $this->getMessage($e);
            if ($e instanceof \app\common\service\nft\CException) {
                $code = $e->getCode() ?: \app\common\service\nft\CException::SYSTEM_ERROR;
            } elseif ($e instanceof HttpException) {
                $code = $e->getStatusCode() == 404
                    ? \app\common\service\nft\CException::NOT_FOUND
                    : \app\common\service\nft\CException::SYSTEM_ERROR;
            } elseif ($e instanceof ValidateException) {
                $code = \app\common\service\nft\CException::PARAM_TYPE;
            }
            if (!$e instanceof \app\common\service\nft\CException && !$this->app->isDebug()) {
                // 非业务异常不泄露细节
                $msg = $this->app->config->get('app.error_message', '系统错误');
            }

            if (config('nft.c_end_style', 'zhenhai') === 'zhenhai') {
                return json([
                    'status'  => false,
                    'code'    => $code,
                    'message' => $msg !== '' ? $msg : '系统错误',
                    'data'    => null,
                ]);
            }
            return json(['code' => $code, 'msg' => $msg, 'time' => time(), 'data' => null]);
        }

        // 其他错误交给系统处理
        return parent::render($request, $e);
    }

    /**
     * 收集异常数据
     */
    protected function convertExceptionToArray(Throwable $exception): array
    {
        if ($this->app->isDebug()) {
            // 调试模式，获取详细的错误信息
            $traces        = [];
            $nextException = $exception;
            do {
                $traces[] = [
                    'name'    => $nextException::class,
                    'file'    => $nextException->getFile(),
                    'line'    => $nextException->getLine(),
                    'code'    => $this->getCode($nextException),
                    'message' => $this->getMessage($nextException),
                    'trace'   => $nextException->getTrace(),
                    'source'  => $this->getSourceCode($nextException),
                ];
            } while ($nextException = $nextException->getPrevious());

            // 循环引用检测并直接置空 traces（比起循环引用导致的报错，置空后开发者能得到更多真实的错误信息）
            if ($this->app->request->isJson()) {
                $json = json_encode($traces, JSON_UNESCAPED_UNICODE);
                if (false === $json && in_array(json_last_error(), [JSON_ERROR_DEPTH, JSON_ERROR_RECURSION])) {
                    $traces = [];
                }
            }

            $data = [
                'code'    => $this->getCode($exception),
                'message' => $this->getMessage($exception),
                'traces'  => $traces,
                'datas'   => $this->getExtendData($exception),
                'tables'  => [
                    'GET Data'            => $this->app->request->get(),
                    'POST Data'           => $this->app->request->post(),
                    'Files'               => $this->app->request->file(),
                    'Cookies'             => $this->app->request->cookie(),
                    'Session'             => $this->app->exists('session') ? $this->app->session->all() : [],
                    'Server/Request Data' => $this->app->request->server(),
                ],
            ];
        } else {
            // 部署模式仅显示 Code 和 Message
            $data = [
                'code'    => $this->getCode($exception),
                'message' => $this->getMessage($exception),
            ];

            if (!$this->app->config->get('app.show_error_msg')) {
                // 不显示详细错误信息
                $data['message'] = $this->app->config->get('app.error_message');
            }
        }

        return $data;
    }
}
