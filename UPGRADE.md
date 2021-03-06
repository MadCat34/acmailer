# Upgrade from 5.x/6.x to 7.x

- **Configuration**

    The configuration structure has changed. Now emails and services are separated. Any service can send any email.

    You can find the new configuration structure [here](https://github.com/acmailer/acmailer#configuration-options), but you can also use this [CLI tool](https://github.com/acmailer/acmailer-tooling) in order to migrate your old configuration to the new structure.

- **Send method expects an argument**

    In previous versions, mail services used to wrap an email to be sent. Now services are stateless and can send multiple emails.

    Now the `send` method expects an argument. When upgrading from a previous version, you will usually move the old **message_options** to a preconfigured email.
    
    Then, anywhere you are calling `$mailService->send()`, you have to replace it by `$mailService->send('my_email_name')`. You can also send anonymous emails created on the fly as explained [here](https://github.com/acmailer/acmailer#send-emails).

- **Controller plugin**

    The controller plugin has been removed in favor of dependency injection. If you are using it in a controller, just make sure to inject the proper mail service.
    
    ```php
    <?php
    class TheControllerFactory
    {
        public function __invoke($container)
        {
            return new TheController($container->get('acmailer.mailservice.company'));
        }
    }
    ```
    
    Then replace the usage inside the controller.
    
    ```php
    <?php
    class TheController
    {
        private $mailService;
        
        public function __construct(AcMailer\Service\MailServiceInterface $mailService)
        {
            $this->mailService = $mailService;
        }
        
        public function sendAction()
        {
            // Replace this
            $this->sendMailCompany(
                'The body',
                'The subject',
                ['recipient_one@domain.com', 'recipient_two@domain.com'],
            );
            
            // By this
            $this->mailService->send([
                'body' => 'The body',
                'subject' => 'The subject',
                'to' => ['recipient_one@domain.com', 'recipient_two@domain.com'],
            ]);
        }
    }
    ```

- **Mail service message**

    Mail services no longer expose a `Zend\Mail\Message` instance. Instead, if you want to override a previously defined config, you have to pass a second argument to the `send` method.
    
    Change this:
    
    ```php
    <?php
    $message = $mailService->getMessage();
    $message->setSubject('This is the subject')
            ->addTo('foobar@example.com')
            ->addTo('another@example.com')
            ->addBcc('hidden@domain.com');
    
    $result = $mailService->send();
    ```
    
    By this:
    
    ```php
    <?php    
    $result = $mailService->send('an_email', [
        'subject' => 'This is the subject',
        'to' => ['foobar@example.com', 'another@example.com'],
        'bcc' => ['hidden@domain.com'],
    ]);
    ```
    
    Other methods designed to edit the email have been removed too, like `setBody()`, `setTemplate()`, `setSubject()`, `setAttachments()` and such.
    
    All the email data is now wrapped in the `AcMailer\Model\Email` object instead.
    
- **Render templates**

    In order to make it compatible with Zend Expressive, it is no longer possible to pass a `Zend\View\Model\ModelInterface` object when defining email templates.
    
    Instead you have to always provide the name of the template. However, you can pass a **layout** param with the name of the parent layout. It'll work in both Expressive and MVC when using zend/view.
    
    Change this:
    
    ```php
    <?php
    $layout = new \Zend\View\Model\ViewModel();
    $layout->setTemplate('application/emails/layout');
    
    $view = new \Zend\View\Model\ViewModel([
        'name' => 'John Doe', 
        'date' => date('Y-m-d')
    ]);
    $view->setTemplate('application/emails/merry-christmas');
  
    $layout->addChild($view);
  
    $mailService->setTemplate($layout);
    $mailService->send();
    ```
    
    By this:
    
    ```php
    <?php
    $mailService->send([
        // ...
        'template' => 'application/emails/merry-christmas',
        'template_params' => [
            'name' => 'John Doe', 
            'date' => date('Y-m-d'),
            'layout' => 'application/emails/layout', 
        ],
    ]);
    ```
    
    You can't used the layout param when using other renderers in Expressive, since they have their own way to inherit templates.
    
- **Exposed services**

    In previous versions, mail services used to have getters and setters to access the composed transport and renderer. They are no longer there.
    
    The only one that keeps existing is the `getEventManager()` method, which exists because mail services implement `Zend\EventManager\EventsCapableInterface`.
    
- **Mail events**

    Mail events used to provide the `MailService` instance that was used to initially send the email. You were able to use it to customize the email before sending it.
    
    Now, since services are stateless, event objects wrap a `AcMailer\Model\Email` instance for that purpose.
    
    Instead of doing this:
    
    ```php
    <?php
    class MyListener extends AcMailer\Event\AbstractMailListener
    {
        public function onPreSend(AcMailer\Event\MailEvent $e)
        {
            $message = $e->getMailService()->getMessage();
            $message->setSubject('This is the subject')
                    ->addTo('foobar@example.com')
                    ->addTo('another@example.com')
                    ->addBcc('hidden@domain.com');
        }
    }
    ```
    
    Now you have to do this:
    
    ```php
    <?php
    class MyListener extends AcMailer\Event\AbstractMailListener
    {
        public function onPreSend(AcMailer\Event\MailEvent $e)
        {
            $email = $e->getEmail();
            $email->setSubject('This is the subject')
                  ->addTo('foobar@example.com')
                  ->addTo('another@example.com')
                  ->addBcc('hidden@domain.com');
        }
    }
    ```
    
    Also, the methods `attachMailListener` and `detachMailListener` in mail services, no longer return `$this`, so it is not possible to perform method chaining anymore.
    
- **Mail results**

    After an email has been sent, a `AcMailer\Result\MailResult` object is returned. These objects used to include a result message as string, which wasn't really useful.
    
    This message is no longer included, but, on the other hand, the `AcMailer\Model\Email` that was sent is included now.
    
- **MailServiceMock**

    Previous versions used to include a `AcMailer\Service\MailServiceMock` which has been removed. You should create your own mocks now.
    
- **Deprecations**

    All classes and methods that were deprecated have been removed in this version.
    
- **Options objects removed**

    The module used to include various options classes that have been removed, since configuration has been simplified and the `AcMailer\Model\Email` model has been included instead.

    It is not very likely that you were using these objects, since they were used during services creation, but if for some reason you were using any of them, you will have to update your code.
    
- **Exceptions while sending an email**

    In previous versions, when the method `send` was called and an exception was thrown, the `EVENT_MAIL_SEND_ERROR` was triggered, and then, if the exception implemented `Zend\Mail\Exception\ExceptionInterface`, it was wrapped in a `AcMailer\Exception\MailException` and rethrown. In any other case, the exception was lost.
    
    Since that's dangerous and can hide potential bugs, the service now always rethrows the exception, whichever the type.
    
- **Mail service aware**

    The `AcMailer\Service\MailServiceAwareInterface` and `AcMailer\Service\MailServiceAwareTrait` files have been removed, because I want to promote constructor dependency injection 
