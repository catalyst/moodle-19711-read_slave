import Modal from 'core/modal';

// Custom modal.
let modal = null;

/**
 * Override Delete Modal
 */
export class OverrideDeleteModal extends Modal {
    static TYPE = "mod_assign/override_delete_modal";
    static TEMPLATE = "mod_assign/override_delete_modal";
}

/**
 * Selectors
 */
const SELECTORS = {
    DELETE_BUTTONS: '.delete-override',
};

export const init = async () => {

    // Create the modal.
    modal = await OverrideDeleteModal.create({});

    // Add event listeners.
    document.querySelectorAll(SELECTORS.DELETE_BUTTONS).forEach(button => {
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            show(event.target);
        });
    });

};

/**
 * Show the modal.
 */
export const show = (target) => {
    //
    modal.show();

};
