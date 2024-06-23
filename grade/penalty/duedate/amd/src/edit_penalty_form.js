// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Handles edit penalty form.
 *
 * @module     gradepenalty_duedate/edit_penalty_form
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as notification from 'core/notification';
import Fragment from 'core/fragment';
import Templates from 'core/templates';

/**
 * Rule js class.
 */
class PenaltyRule {
    constructor() {
        this.overdueby = 0;
        this.penalty = 0;
    }
}

/**
 * Selectors
 */
const SELECTORS = {
    FORM_CONTAINER: '#penalty_rule_form_container',
    INSERT_BUTTON: '.insertbelow',
    DELETE_BUTTON: '.deleterulebuttons',
};

/**
 * Register click event for delete and insert buttons.
 */
const registerEventListeners = () => {
    // Find all delete buttons and add event listeners.
    const deleteButtons = document.querySelectorAll(SELECTORS.DELETE_BUTTON);
    deleteButtons.forEach(button => {
        button.addEventListener('click', e => {
            e.preventDefault();
            deleteRule(e.target);
        });
    });

    // Find all insert below buttons and add event listeners.
    const insertBelowButtons = document.querySelectorAll(SELECTORS.INSERT_BUTTON);
    insertBelowButtons.forEach(button => {
        button.addEventListener('click', e => {
            e.preventDefault();
            insertRule(e.target);
        });
    });
};

/**
 * Delete a rule group.
 *
 * @param {Object} target
 */
const deleteRule = target => {
    // Get all form data
    let params = buildFormParams();

    // Get the rule number from the name of clicked button.
    let rulenumber = getRuleNumber(target);

    // Remove the penalty rule.
    let penaltyRules = JSON.parse(params.penaltyrules);
    penaltyRules.splice(rulenumber, 1);
    // Check if the lis is empty.
    if (penaltyRules.length === 0) {
        // Add an empty default penalty rule.
        penaltyRules.push(new PenaltyRule());
    }
    penaltyRules = JSON.stringify(penaltyRules);
    params.penaltyrules = penaltyRules;

    loadPenaltyRuleForm(params.contextid, params);
};

/**
 * Insert a rule group below the clicked button.
 *
 * @param {Object} target
 */
const insertRule = target => {
    // Get all form data
    let params = buildFormParams();

    // Get the rule number from the name of clicked button.
    let rulenumber = getRuleNumber(target);

    // Insert a new penalty rule.
    let penaltyRule = new PenaltyRule();
    let penaltyRules = JSON.parse(params.penaltyrules);
    penaltyRules.splice(rulenumber + 1, 0, penaltyRule);
    penaltyRules = JSON.stringify(penaltyRules);
    params.penaltyrules = penaltyRules;

    loadPenaltyRuleForm(params.contextid, params);
};

/**
 * Get rule number.
 *
 * @param {Object} target
 */
const getRuleNumber = target => {
    let name = target.getAttribute('name');
    let rulenumber = name.match(/\d+/)[0];
    return parseInt(rulenumber);
};

/**
 * Build form parameters for loading fragment.
 *
 * @return {Object} form params
 */
const buildFormParams = () => {
    // Get the penalty rule form in its container.
    let container = document.querySelector(SELECTORS.FORM_CONTAINER);
    let form = container.querySelector('form');

    // Get all form data
    let formData = new FormData(form);

    // Get context id.
    let contextid = formData.get('contextid');

    // Get group count.
    let groupCount = formData.get('rulegroupcount');

    // Create list of penalty rules.
    let penaltyRules = [];

    // Current penalty rules.
    for (let i = 0; i < groupCount; i++) {
        let penaltyRule = new PenaltyRule();
        penaltyRule.overdueby = formData.get(`overdueby[${i}][number]`) * formData.get(`overdueby[${i}][timeunit]`);
        penaltyRule.penalty = formData.get(`penalty[${i}]`);
        penaltyRules.push(penaltyRule);
    }

    return {
        contextid: contextid,
        penaltyrules: JSON.stringify(penaltyRules),
        finalpenaltyrule: formData.get('finalpenaltyrule'),
    };
};

/**
 * Load the penalty rule form.
 *
 * @param {integer} contextid
 * @param {object} params
 */
const loadPenaltyRuleForm = (contextid, params) => {
    Fragment.loadFragment('gradepenalty_duedate', 'penalty_rule_form', contextid, params)
        .done((html, js) => {
            // Replace the form with the new form.
            let formContainer = document.querySelector(SELECTORS.FORM_CONTAINER);
            Templates.replaceNodeContents(formContainer, html, js);
        }).fail(notification.exception);
};

/**
 * Initialize the js.
 */
export const init = () => {
    registerEventListeners();
};
