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
     *
     * @var \Symfony\Component\HttpFoundation\Response
     */
    protected $response;

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
        // Replace SendResponseListener
        $events = $application->getEventManager();
        $events->clearListeners(MvcEvent::EVENT_FINISH);
        $events->attach(MvcEvent::EVENT_FINISH, array($this, 'onFinish'), -10000);

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
        $this->setRequest($request);
        $this->application->bootstrap($this->listeners)->run();
        return $this->getResponse();
    }

    /**
     * Sets the request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return null
     */
    protected function setRequest(SymfonyRequest $request)
    {
        $zendRequest = self::createZendRequest($request);

        $serviceManager = $this->application->getServiceManager();
        $serviceManager->setAllowOverride(true);
        $serviceManager->setService('Request', $zendRequest);
        $serviceManager->setAllowOverride(false);
    }

    /**
     * Removes the response from this instance and returns it.
     *
     * @return \Symfony\Component\HttpFoundation\Response $response
     */
    protected function getResponse()
    {
        $response = $this->response;
        $this->response = null;
        return $response;
    }

    /**
     * Listens to the finish event, reads the response.
     *
     * @param \Zend\Mvc\MvcEvent $event
     * @return null
     */
    public function onFinish(MvcEvent $event)
    {
        $response = $event->getResponse();
        if (!$response instanceof ZendResponse) {
            return;
        }
        $event->stopPropagation(true);

        $this->response = self::createSymfonyResponse($response);
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
