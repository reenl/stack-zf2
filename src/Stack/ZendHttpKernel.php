<?php
namespace Stack;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use Zend\Mvc\Application;
use Zend\Mvc\Service;
use Zend\ServiceManager\ServiceManager;
use Zend\Mvc\MvcEvent;
use Zend\Http\Response as ZendResponse;
use Zend\Http\PhpEnvironment\Request as ZendRequest;

class ZendHttpKernel implements HttpKernelInterface
{
    /**
     *
     * @var \Zend\Mvc\Application
     */
    protected $application;

    /**
     *
     * @var array
     */
    protected $listeners = array();

    /**
     * Fully compatable with \Zend\Mvc\Application::init. However this function
     * does not call bootstrap.
     *
     * @param array $configuration
     * @return \Stack\ZendHttpKernel
     */
    public static function init($configuration = array())
    {
        $smConfig = isset($configuration['service_manager']) ? $configuration['service_manager'] : array();

        $serviceManager = new ServiceManager();
        $cfg = new Service\ServiceManagerConfig($smConfig);
        $cfg->configureServiceManager($serviceManager);
        $serviceManager->setService('ApplicationConfig', $configuration);
        $serviceManager->get('ModuleManager')->loadModules();
        $application = $serviceManager->get('Application');

        $instance = new static($application);
        if (isset($configuration['listeners'])) {
            $instance->setListeners($configuration['listeners']);
        }
        return $instance;
    }

    /**
     * The Zend\Mvc\Application can be injected here if you wish not to use
     * the ::init function.
     *
     * This class will boostrap the application. If you call bootstrap yourself
     * events will be triggered, without any use.
     *
     * @param \Zend\Mvc\Application $application
     * @return \Stack\ZendHttpKernel
     */
    public function __construct(Application $application)
    {
        // Add the application.
        $this->application = $application;
        return $this;
    }

    /**
     * These listeners are injected in the bootstrap method.
     *
     * @param array $listeners
     * @return \Stack\ZendHttpKernel
     */
    public function setListeners(array $listeners)
    {
        $this->listeners = $listeners;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(SymfonyRequest $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $this->setSymfonyRequest($request);
        /*
         * ZF 2.2 exposes the request object when bootstrapping. With that fixed
         * we can move this back to init.
         */
        $this->bootstrap();

        // Register if exceptions should be caught into the ServiceManager.
        $this->setCatchExceptions($catch);

        // Run the application.
        $this->run();

        return $this->getSymfonyResponse();
    }

    /**
     *
     * @return \Stack\ZendHttpKernel
     */
    public function bootstrap()
    {
        $this->application->bootstrap($this->listeners);

        // Replace SendResponseListener
        $events = $this->application->getEventManager();
        $events->clearListeners(MvcEvent::EVENT_FINISH);

        $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'throwException'));
        $events->attach(MvcEvent::EVENT_RENDER_ERROR, array($this, 'throwException'));

        return $this;
    }

    public function throwException(MvcEvent $event)
    {
        $sm = $this->application->getServiceManager();
        if ($sm->get('KernelCatchExceptions')) {
            return;
        }

        $event->stopPropagation(true);

        $ex = $event->getParam('exception');
        if ($ex !== null) {
            throw $ex;
        }

        $error = $event->getError();
        $message = 'Unkown error.';
        if ($error) {
            $message .= ' ('.$error.')';
        }
        $class = '\Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException';

        switch ($error) {
            case Application::ERROR_CONTROLLER_NOT_FOUND:
            case Application::ERROR_CONTROLLER_INVALID:
            case Application::ERROR_ROUTER_NO_MATCH:
                $message = '404 not found: '.$error;
                $class = '\Symfony\Component\HttpKernel\Exception\NotFoundHttpException';
                break;
        }
        throw new $class($message);
    }

    /**
     * Run the application.
     *
     * Our run function returns null until ZF decides what to return at
     * \Zend\Mvc\Application::run
     *
     * @return null
     */
    public function run()
    {
        $this->application->run();
    }

    /**
     * Sets the request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return null
     */
    protected function setSymfonyRequest(SymfonyRequest $request)
    {
        $zendRequest = self::createZendRequest($request);

        $serviceManager = $this->application->getServiceManager();
        $serviceManager->setAllowOverride(true);
        $serviceManager->setService('Request', $zendRequest);
        $serviceManager->setAllowOverride(false);
    }

    /**
     * Adds a value to the ServiceManager to indicate if an exception must be
     * thrown or caught.
     *
     * @param boolean $catch
     * @return null
     */
    protected function setCatchExceptions($catch)
    {
        $serviceManager = $this->application->getServiceManager();
        $serviceManager->setAllowOverride(true);
        $serviceManager->setService('KernelCatchExceptions', $catch);
        $serviceManager->setAllowOverride(false);
    }

    /**
     * Gets the response from the service manager and convert it to Symfony.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getSymfonyResponse()
    {
        $zendResponse = $this->application->getResponse();
        return self::createSymfonyResponse($zendResponse);
    }

    /**
     * Converts a symfony request to a zend request.
     *
     * @todo Don't use from-to string.
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Zend\Http\PhpEnvironment\Request
     */
    protected static function createZendRequest(SymfonyRequest $request)
    {
        return ZendRequest::fromString((string)$request);
    }


    /**
     * Converts a zend response to a symfony response.
     *
     * @param \Zend\Http\Response $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected static function createSymfonyResponse(ZendResponse $response)
    {
        $content = $response->getContent();
        $status = $response->getStatusCode();
        $zendHeaders = $response->getHeaders();
        $headers = array();
        foreach ($zendHeaders as $header) {
            $name = $header->getFieldName();
            $headers[$name] = $header->getFieldValue();
        }
        return new SymfonyResponse($content, $status, $headers);
    }
}
