/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for Docker update progress tracking.
 *
 * Polls the health check endpoint to detect when the container restarts
 * after a Watchtower-triggered update. Drives the step timeline UI
 * with timestamps, matching the git update progress style.
 */
export default class extends Controller {
    static values = {
        healthUrl: String,
        previousVersion: { type: String, default: 'unknown' },
        pollInterval: { type: Number, default: 5000 },
        maxWaitTime: { type: Number, default: 600000 }, // 10 minutes
        // Translated UI strings (passed from Twig template)
        textPulling: { type: String, default: 'Waiting for Watchtower to pull the new image...' },
        textPullingDetail: { type: String, default: 'Watchtower is checking for and downloading the latest Docker image...' },
        textRestarting: { type: String, default: 'Container is restarting with the new image...' },
        textRestartingDetail: { type: String, default: 'The container is being recreated with the updated image. This may take a moment...' },
        textSuccess: { type: String, default: 'Update Complete!' },
        textSuccessDetail: { type: String, default: 'Part-DB has been updated successfully via Docker.' },
        textTimeout: { type: String, default: 'Update Taking Longer Than Expected' },
        textTimeoutDetail: { type: String, default: 'The update may still be in progress. Check your Docker logs for details.' },
        textStepPull: { type: String, default: 'Pull Image' },
        textStepRestart: { type: String, default: 'Restart Container' },
    };

    static targets = [
        // Header
        'headerWhale', 'titleIcon',
        'statusText', 'statusSubtext',
        'progressBar', 'elapsedTime',
        // Alerts
        'stepAlert', 'stepName', 'stepMessage',
        'successAlert', 'timeoutAlert', 'errorAlert', 'errorMessage', 'warningAlert',
        // Step timeline (multi-target arrays)
        'stepRow', 'stepIcon', 'stepDetail', 'stepTime',
        // Version display
        'newVersion', 'previousVersion',
        // Actions
        'actions',
    ];

    // Step definitions: name -> { index, progress% }
    static STEPS = {
        trigger:  { index: 0, progress: 15 },
        pull:     { index: 1, progress: 30 },
        stop:     { index: 2, progress: 50 },
        restart:  { index: 3, progress: 65 },
        health:   { index: 4, progress: 80 },
        verify:   { index: 5, progress: 100 },
    };

    connect() {
        this.serverWentDown = false;
        this.serverCameBack = false;
        this.startTime = Date.now();
        this.timer = null;
        this.currentStep = 'pull'; // trigger is already done
        this.stepTimestamps = { trigger: this.formatTime(new Date()) };
        this.consecutiveSuccessCount = 0;

        // Set the trigger step timestamp
        this.setStepTimestamp(0, this.stepTimestamps.trigger);

        this.poll();
    }

    disconnect() {
        if (this.timer) {
            clearTimeout(this.timer);
        }
    }

    createTimeoutSignal(ms) {
        if (typeof AbortSignal.timeout === 'function') {
            return AbortSignal.timeout(ms);
        }
        const controller = new AbortController();
        setTimeout(() => controller.abort(), ms);
        return controller.signal;
    }

    async poll() {
        const elapsed = Date.now() - this.startTime;
        this.updateElapsedTime(elapsed);

        if (elapsed > this.maxWaitTimeValue) {
            this.showTimeout();
            return;
        }

        try {
            const response = await fetch(this.healthUrlValue, {
                cache: 'no-store',
                signal: this.createTimeoutSignal(4000),
            });

            if (response.ok) {
                let data;
                try {
                    data = await response.json();
                } catch (parseError) {
                    this.schedulePoll();
                    return;
                }

                if (this.serverWentDown) {
                    // Server came back! Move through health check -> verify
                    if (!this.serverCameBack) {
                        this.serverCameBack = true;
                        this.advanceToStep('health');
                    }

                    this.consecutiveSuccessCount++;

                    // Wait for 2 consecutive successes to confirm stability
                    if (this.consecutiveSuccessCount >= 2) {
                        this.showSuccess(data.version);
                        return;
                    }
                } else {
                    // Server still up - Watchtower pulling image
                    this.showPulling();
                }
            } else if (response.status === 503) {
                // Maintenance mode or shutting down
                this.serverWentDown = true;
                this.consecutiveSuccessCount = 0;
                this.advanceToStep('stop');
            } else {
                if (this.serverWentDown) {
                    this.showRestarting();
                } else {
                    this.showPulling();
                }
            }
        } catch (e) {
            // Connection refused = container is down
            if (!this.serverWentDown) {
                this.serverWentDown = true;
                this.advanceToStep('stop');
            }
            this.consecutiveSuccessCount = 0;
            this.showRestarting();
        }

        this.schedulePoll();
    }

    schedulePoll() {
        this.timer = setTimeout(() => this.poll(), this.pollIntervalValue);
    }

    /**
     * Advance the step timeline to a specific step.
     * Marks all previous steps as complete with timestamps.
     */
    advanceToStep(stepName) {
        const steps = this.constructor.STEPS;
        const targetIndex = steps[stepName]?.index;
        if (targetIndex === undefined) return;

        const stepNames = Object.keys(steps);
        const now = this.formatTime(new Date());

        for (let i = 0; i < stepNames.length; i++) {
            const name = stepNames[i];

            if (i < targetIndex) {
                // Completed step
                this.markStepComplete(i, this.stepTimestamps[name] || now);
                if (!this.stepTimestamps[name]) {
                    this.stepTimestamps[name] = now;
                }
            } else if (i === targetIndex) {
                // Current active step
                this.markStepActive(i);
                this.stepTimestamps[name] = now;
                this.setStepTimestamp(i, now);
                this.currentStep = name;
            }
            // Steps after targetIndex remain pending (no change needed)
        }

        // Update progress bar
        this.updateProgressBar(steps[stepName].progress);
    }

    showPulling() {
        if (this.hasStatusTextTarget) {
            this.statusTextTarget.textContent = this.textPullingValue;
        }
        if (this.hasStepNameTarget) {
            this.stepNameTarget.textContent = this.textStepPullValue;
        }
        if (this.hasStepMessageTarget) {
            this.stepMessageTarget.textContent = this.textPullingDetailValue;
        }
        this.updateProgressBar(30);
    }

    showRestarting() {
        // Advance to restart step if we haven't already
        if (this.currentStep !== 'restart' && this.currentStep !== 'health' && this.currentStep !== 'verify') {
            this.advanceToStep('restart');
        }

        if (this.hasStatusTextTarget) {
            this.statusTextTarget.textContent = this.textRestartingValue;
        }
        if (this.hasStepNameTarget) {
            this.stepNameTarget.textContent = this.textStepRestartValue;
        }
        if (this.hasStepMessageTarget) {
            this.stepMessageTarget.textContent = this.textRestartingDetailValue;
        }
    }

    showSuccess(newVersion) {
        // Advance all steps to complete
        const steps = this.constructor.STEPS;
        const stepNames = Object.keys(steps);
        const now = this.formatTime(new Date());

        for (let i = 0; i < stepNames.length; i++) {
            const name = stepNames[i];
            this.markStepComplete(i, this.stepTimestamps[name] || now);
        }

        this.updateProgressBar(100);

        // Update whale animation
        if (this.hasHeaderWhaleTarget) {
            this.headerWhaleTarget.classList.add('success');
        }
        if (this.hasTitleIconTarget) {
            this.titleIconTarget.className = 'fas fa-check-circle text-success';
        }

        if (this.hasStatusTextTarget) {
            this.statusTextTarget.textContent = this.textSuccessValue;
        }
        if (this.hasStatusSubtextTarget) {
            this.statusSubtextTarget.textContent = this.textSuccessDetailValue;
        }

        // Hide step alert, show success alert
        this.toggleTarget('stepAlert', false);
        this.toggleTarget('successAlert', true);
        this.toggleTarget('warningAlert', false);
        this.toggleTarget('actions', true);

        if (this.hasNewVersionTarget) {
            this.newVersionTarget.textContent = newVersion || 'latest';
        }
        if (this.hasPreviousVersionTarget) {
            this.previousVersionTarget.textContent = this.previousVersionValue;
        }
    }

    showTimeout() {
        this.updateProgressBar(0);

        if (this.hasHeaderWhaleTarget) {
            this.headerWhaleTarget.classList.add('timeout');
        }
        if (this.hasTitleIconTarget) {
            this.titleIconTarget.className = 'fas fa-exclamation-triangle text-warning';
        }

        if (this.hasStatusTextTarget) {
            this.statusTextTarget.textContent = this.textTimeoutValue;
        }
        if (this.hasStatusSubtextTarget) {
            this.statusSubtextTarget.textContent = this.textTimeoutDetailValue;
        }

        this.toggleTarget('stepAlert', false);
        this.toggleTarget('timeoutAlert', true);
        this.toggleTarget('warningAlert', false);
        this.toggleTarget('actions', true);
    }

    // --- Step timeline helpers ---

    markStepComplete(index, timestamp) {
        if (this.stepIconTargets[index]) {
            this.stepIconTargets[index].className = 'fas fa-check-circle text-success me-3';
        }
        if (this.stepRowTargets[index]) {
            this.stepRowTargets[index].classList.remove('text-muted');
        }
        if (timestamp) {
            this.setStepTimestamp(index, timestamp);
        }
    }

    markStepActive(index) {
        if (this.stepIconTargets[index]) {
            this.stepIconTargets[index].className = 'fas fa-spinner fa-spin text-primary me-3';
        }
        if (this.stepRowTargets[index]) {
            this.stepRowTargets[index].classList.remove('text-muted');
        }
    }

    setStepTimestamp(index, time) {
        if (this.stepTimeTargets[index]) {
            this.stepTimeTargets[index].textContent = time;
        }
    }

    // --- UI helpers ---

    toggleTarget(name, show) {
        const hasMethod = 'has' + name.charAt(0).toUpperCase() + name.slice(1) + 'Target';
        if (this[hasMethod]) {
            this[name + 'Target'].classList.toggle('d-none', !show);
        }
    }

    updateProgressBar(percent) {
        if (this.hasProgressBarTarget) {
            const bar = this.progressBarTarget;
            // Remove all width classes
            bar.classList.remove('progress-w-0', 'progress-w-15', 'progress-w-30', 'progress-w-50', 'progress-w-65', 'progress-w-80', 'progress-w-100');
            bar.classList.add('progress-w-' + percent);
            bar.textContent = percent + '%';
            bar.setAttribute('aria-valuenow', percent);

            bar.classList.remove('bg-success', 'bg-danger', 'progress-bar-striped', 'progress-bar-animated');
            if (percent === 100) {
                bar.classList.add('bg-success');
            } else if (percent === 0) {
                bar.classList.add('bg-danger');
            } else {
                bar.classList.add('progress-bar-striped', 'progress-bar-animated');
            }
        }
    }

    updateElapsedTime(elapsed) {
        if (this.hasElapsedTimeTarget) {
            const seconds = Math.floor(elapsed / 1000);
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            this.elapsedTimeTarget.textContent = minutes > 0
                ? `${minutes}m ${remainingSeconds}s`
                : `${remainingSeconds}s`;
        }
    }

    formatTime(date) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
}
