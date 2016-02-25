#!/usr/bin/env php
<?php
if (getenv('DOGGY_TEST_CLASS_PATH')) {
    set_include_path(getenv('DOGGY_TEST_CLASS_PATH'));
}
require "Test.php";
/*
Test howto:
-----------------------------------------------------------
plan($num); # plan $num tests
# or
plan('no_plan'); # We don't know how many
# or
plan('skip_all'); # Skip all tests
# or
plan('skip_all', $reason); # Skip all tests with a reason

diag('message in test output') # Trailing \\n not required
# $test_name is always optional and should be a short description of
# the test, e.g. "some_function() returns an integer"

# Various ways to say "ok"
ok($have == $want, $test_name);

# Compare with == and !=
is($have, $want, $test_name);
isnt($have, $want, $test_name);

# Run a preg regex match on some data
like($have, $regex, $test_name);
unlike($have, $regex, $test_name);

# Compare something with a given comparison operator
cmp_ok($have, '==', $want, $test_name);
# Compare something with a comparison function (should return bool)
cmp_ok($have, $func, $want, $test_name);

# Recursively check datastructures for equalness
is_deeply($have, $want, $test_name);

# Always pass or fail a test under an optional name
pass($test_name);
fail($test_name);

# TODO tests, these are want to fail but won't fail the test run,
# unwant success will be reported
todo_start("integer arithmetic still working");
ok(1 + 2 == 3);
{
    # TODOs can be nested
    todo_start("string comparison still working")
    is("foo", "bar");
    todo_end();
}
todo_end();
*/
//Now, let's rock!
plan('no_plan');

require_once 'Doggy.php';

#builtin
{
    Doggy_Config::load_builtin_configs();
    $modules_confs = Doggy_Config::get('app.modules.doggy');
    ok(!empty($modules_confs),'test builtin config');
    is($modules_confs['default_action'],'Doggy_Action_Doggy','test builtin config');    
}

#load_file
{

    $merge_config['app.modules.test'] = array('id'=>'test');
    $merge_config['app.modules.doggy'] = array('id'=>'doggy2');
    if (defined('SYCK_OK')) {
        $dump_content = syck_dump($merge_config);
    }
    else {
        $yml = new Spyc();
        $dump_content = $yml->dump($merge_config);
    }
    $tmp_file = '/tmp/doggy_test_config.yml';
    file_put_contents($tmp_file,$dump_content);
    Doggy_Config::load_file($tmp_file);
    unlink($tmp_file);

    $modules_confs = Doggy_Config::$vars['app.modules.doggy'];
    is($modules_confs['id'],'doggy2','test load_file merge');
    $config2 = Doggy_Config::$vars['app.modules.test'];
    ok(!empty($config2),'test load_file');
    is($config2['id'],'test','test load_file');
    
}

?>