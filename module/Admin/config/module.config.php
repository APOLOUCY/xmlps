<?php
namespace Admin;

return array(
    'router' => array(
        'routes' => array(
            'admin' => array(
                'type' => 'literal',
                'options' => array(
                    'route' => '/admin',
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'user_management' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => '/user-management/:action[/page/:page][/user/:id]',
                            'constraints' => array(
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'page' => '[0-9]*',
                                'id' => '[0-9]+',
                            ),
                            'defaults' => array(
                                'controller' => 'Admin\Controller\UserManagement',
                                'action' => 'list',
                                'page' => 1,
                            ),
                        ),
                    ),
                    'system_log' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => '/system-log/:action[/page/:page]',
                            'constraints' => array(
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'page' => '[0-9]*',
                            ),
                            'defaults' => array(
                                'controller' => 'Admin\Controller\SystemLog',
                                'action' => 'list',
                                'page' => 1,
                            ),
                        ),
                    ),
                )
            ),
        ),
    ),
    'view_manager' => array(
        'template_path_stack' => array(
            'admin' => __DIR__ . '/../view'
        ),
    ),

    'acl' => array(
        'resources' => array(
            'controller:Admin\Controller\SystemLog:list',
            'controller:Admin\Controller\UserManagement:edit',
            'controller:Admin\Controller\UserManagement:remove',
            'controller:Admin\Controller\UserManagement:list',
        ),
        'rules' => array(
            array('allow', 'administrator', 'controller:Admin\Controller\SystemLog:list'),
            array('allow', 'administrator', 'controller:Admin\Controller\UserManagement:edit'),
            array('allow', 'administrator', 'controller:Admin\Controller\UserManagement:remove'),
            array('allow', 'administrator', 'controller:Admin\Controller\UserManagement:list'),
        ),
    )
);
