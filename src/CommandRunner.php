<?php

namespace BinaryBuilds\NovaAdvancedCommandRunner;

use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

class CommandRunner extends Tool
{
    /**
     * Perform any tasks that need to happen when the tool is booted.
     *
     * @return void
     */
    public function boot()
    {
        Nova::script('nova-advanced-command-runner', __DIR__ . '/../dist/js/tool.js');
//        Nova::style('nova-advanced-command-runner', __DIR__ . '/../dist/css/tool.css');
    }

    /**
     * Build the view that renders the navigation links for the tool.
     *
     * @return \Illuminate\View\View
     */
    public function renderNavigation()
    {
        return view('nova-advanced-command-runner::navigation');
    }
}
