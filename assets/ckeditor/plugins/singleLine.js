import Plugin from '@ckeditor/ckeditor5-core/src/plugin';

export default class SingleLinePlugin extends Plugin {
    init() {
        const editor = this.editor;
        const view = editor.editing.view;
        const viewDocument = view.document;

        //Listen to enter presses
        this.listenTo( viewDocument, 'enter', ( evt, data ) => {
            //If user presses enter, prevent the enter action
            evt.stop();
        }, { priority: 'high' } );

        //And clipboard pastes
        this.listenTo( viewDocument, 'clipboardInput', ( evt, data ) => {
            let dataTransfer = data.dataTransfer;

            //Clean text input (replace newlines with spaces)
            let input = dataTransfer.getData("text");
            let cleaned = input.replace(/\r?\n/g, ' ');

            //We can not use the dataTransfer.setData method because the old object is somehow protected
            data.dataTransfer = new DataTransfer();
            data.dataTransfer.setData("text", cleaned);
            
        }, { priority: 'high' } );
    }
}