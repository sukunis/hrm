#!/usr/bin/env python
# -*- coding: utf-8 -*-#
# @(#)HRM_QueueManager.py
#
"""
The prototype of a new GC3Pie-based Queue Manager for HRM.
"""

# TODO:
# - check if a sane (usable) gc3pie configuration exists!
# - move processed jobfiles to cur/done
# - let gc3pie decide when to dispatch a job (currently the call to run_job()
#   is blocking and thus the whole thing is limited to single sequential job
#   instances, even if more resources were available

# stdlib imports
import sys
import time

# GC3Pie imports
import gc3libs

import pyinotify
import argparse
import pprint

import HRM

import logging
# loglevel = logging.DEBUG
loglevel = logging.WARN
gc3libs.configure_logger(loglevel, "qmgc3")
warn = gc3libs.log.warn


class EventHandler(pyinotify.ProcessEvent):

    """Handler for pyinotify filesystem events."""

    def __init__(self, joblist):
        self.joblist = joblist

    def process_IN_CREATE(self, event):
        warn("Found new jobfile '%s', processing..." % event.pathname)
        job = HRM.JobDescription(event.pathname, 'file')
        pprint.pprint(job)
        self.joblist.append(job)
        # warn(self.joblist)


class HucoreDeconvolveApp(gc3libs.Application):

    """App object for 'hucore' deconvolution jobs.

    This application calls `hucore` with a given template file and retrives the
    stdout/stderr in a file named `stdout.txt` plus the directories `resultdir`
    and `previews` into a directory `deconvolved` inside the current directory.
    """

    def __init__(self, job):
        warn('Instantiating a HucoreDeconvolveApp:\n%s' % job)
        # we need to add the template (with the local path) to the list of
        # files that need to be transferred to the system running hucore:
        job['infiles'].append(job['template'])
        # for the execution on the remote host, we need to strip all paths from
        # this string as the template file will end up in the temporary
        # processing directory together with all the images:
        templ_on_tgt = job['template'].split('/')[-1]
        gc3libs.Application.__init__(
            self,
            arguments = [job['exec'], '-template', templ_on_tgt],
            inputs = job['infiles'],
            outputs = ['resultdir', 'previews'],
            output_dir = './deconvolved',
            stderr = 'stdout.txt', # combine stdout & stderr
            stdout = 'stdout.txt')


class HucorePreviewgenApp(gc3libs.Application):

    """App object for 'hucore' image preview generation jobs."""

    # stub!
    pass


class HucorePreviewgenApp(gc3libs.Application):

    """App object for 'hucore' SNR estimation jobs."""

    # stub!
    pass


def run_job(engine, job):
    """Run a job in a singlethreaded and blocking manner via GC3Pie.

    NOTE: this doesn't mean the process executed during this job is
    singlethreaded, it just means that currently no more than one job is run
    *at a time*.
    """
    app = HucoreDeconvolveApp(job)

    # Add your application to the engine. This will NOT submit your application
    # yet, but will make the engine *aware* of the application.
    engine.add(app)

    # Periodically check the status of your application.
    laststate = app.execution.state
    curstate = app.execution.state
    while laststate != gc3libs.Run.State.TERMINATED:
        # `Engine.progress()` will do the GC3Pie magic: submit new jobs, update
        # status of submitted jobs, get results of terminating jobs etc...
        engine.progress()
        curstate = app.execution.state
        if not (curstate == laststate):
            warn("Job in status %s " % curstate)

        laststate = app.execution.state
        # Wait a few seconds...
        time.sleep(1)
    warn("Job is now terminated.")
    warn("The output of the application is in `%s`." %  app.output_dir)


def main():
    argparser = argparse.ArgumentParser(description=__doc__)
    argparser.add_argument('-s', '--spooldir', required=True,
        help='spooling directory for new jobfiles')
    argparser.add_argument('-r', '--resource', required=False,
        help='GC3Pie resource name')
    try:
        args = argparser.parse_args()
    except IOError as e:
        argparser.error(str(e))

    joblist = []

    wm = pyinotify.WatchManager() # Watch Manager
    mask = pyinotify.IN_CREATE # watched events
    notifier = pyinotify.ThreadedNotifier(wm, EventHandler(joblist))
    notifier.start()
    watchdir = args.spooldir
    wdd = wm.add_watch(watchdir, mask, rec=False)

    warn('Creating an instance of a GC3Pie engine using the configuration '
        'file present in your home directory.')
    engine = gc3libs.create_engine()
    # select a specific resource if requested on the cmdline:
    if args.resource:
        engine.select_resource(args.resource)

    print('Added watch to "%s", press Ctrl-C to abort.' % watchdir)
    # FIXME: Ctrl-C while a job is running leaves it alone (and thus as well
    # the files transferred for / generated from processing)
    while True:
        try:
            if len(joblist) > 0:
                run_job(engine, joblist.pop(0))
            time.sleep(1)
        except KeyboardInterrupt:
            break

    print('Cleaning up.')
    print(joblist)
    wm.rm_watch(wdd.values())
    notifier.stop()

if __name__ == "__main__":
    sys.exit(main())