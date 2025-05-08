<?php
/**
 * Register all actions and filters for the plugin
 *
 * @link       https://shippingsmile.com/anasrharrass
 * @since      1.0.0
 *
 * @package    Booster
 * @subpackage Booster/includes
 */

declare(strict_types=1);

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    Booster
 * @subpackage Booster/includes
 * @author     anas <anas@shippingsmile.com>
 */
class Booster_Loader {

    /**
     * The array of actions registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @phpstan-var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}> $actions
     * @var      array $actions    The actions registered with WordPress to fire when the plugin loads.
     */
    protected array $actions;

    /**
     * The array of filters registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @phpstan-var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}> $filters
     * @var      array $filters    The filters registered with WordPress to fire when the plugin loads.
     */
    protected array $filters;

    /**
     * Initialize the collections used to maintain the actions and filters.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->actions = [];
        $this->filters = [];
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @since    1.0.0
     * @param    string               $hook             The name of the WordPress action that is being registered.
     * @param    object               $component        A reference to the instance of the object on which the action is defined.
     * @param    string               $callback         The name of the method on the $component.
     * @param    int                  $priority         Optional. The priority at which the function should be fired. Default is 10.
     * @param    int                  $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function add_action(string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1): void {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @since    1.0.0
     * @param    string               $hook             The name of the WordPress filter that is being registered.
     * @param    object               $component        A reference to the instance of the object on which the filter is defined.
     * @param    string               $callback         The name of the method on the $component.
     * @param    int                  $priority         Optional. The priority at which the function should be fired. Default is 10.
     * @param    int                  $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function add_filter(string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1): void {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * A utility function that is used to register the actions and hooks into a single
     * collection.
     *
     * @since    1.0.0
     * @access   private
     * @phpstan-param array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}> $hooks
     * @param    array<mixed>         $hooks The collection of hooks that is being registered (that is, actions or filters).
     * @param    string               $hook             The name of the WordPress filter that is being registered.
     * @param    object               $component        A reference to the instance of the object on which the filter is defined.
     * @param    string               $callback         The name of the method on the $component.
     * @param    int                  $priority         The priority at which the function should be fired.
     * @param    int                  $accepted_args    The number of arguments that should be passed to the $callback.
     * @phpstan-return array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
     * @return   array<mixed> The collection of actions and filters registered with WordPress.
     */
    private function add(array $hooks, string $hook, object $component, string $callback, int $priority, int $accepted_args): array {
        $hooks[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress.
     *
     * @since    1.0.0
     */
    public function run(): void {
        foreach ($this->filters as $filter_item) {
            /** @var callable $callable_array */ // Help PHPStan understand this becomes a callable
            $callable_array = [$filter_item['component'], $filter_item['callback']];

            if (is_callable($callable_array)) { // This check is more direct
                add_filter(
                    $filter_item['hook'],
                    $callable_array,
                    $filter_item['priority'],
                    $filter_item['accepted_args']
                );
            } else {
                 $component_class = get_class($filter_item['component']);
                 error_log(sprintf(
                    'Booster_Loader: Method \'%s\' is not callable on component \'%s\' for filter \'%s\'.',
                    $filter_item['callback'],
                    $component_class,
                    $filter_item['hook']
                ));
            }
        }

        foreach ($this->actions as $action_item) {
            /** @var callable $callable_array */ // Help PHPStan understand this becomes a callable
            $callable_array = [$action_item['component'], $action_item['callback']];

            if (is_callable($callable_array)) { // This check is more direct
                add_action(
                    $action_item['hook'],
                    $callable_array,
                    $action_item['priority'],
                    $action_item['accepted_args']
                );
            } else {
                $component_class = get_class($action_item['component']);
                error_log(sprintf(
                    'Booster_Loader: Method \'%s\' is not callable on component \'%s\' for action \'%s\'.',
                    $action_item['callback'],
                    $component_class,
                    $action_item['hook']
                ));
            }
        }
    }
}