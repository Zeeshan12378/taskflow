@props([
    'model',
    'value' => '',
    'id' => 'rich-text',
    'label' => 'Rich text editor',
    'placeholder' => 'Write something...',
    'minHeight' => '7rem',
    'compact' => false,
])

<div x-data="{
    model: @js($model),
    initialValue: @js(\App\Support\RichText::sanitize($value)),
    menu: null,
    savedRange: null,
    init() { this.$refs.editor.innerHTML = this.initialValue },
    sync() { this.$wire.set(this.model, this.$refs.editor.innerHTML) },
    rememberSelection() {
        const selection = window.getSelection();
        if (selection?.rangeCount && this.$refs.editor.contains(selection.anchorNode)) this.savedRange = selection.getRangeAt(0).cloneRange();
    },
    restoreSelection() {
        this.$refs.editor.focus();
        const selection = window.getSelection();
        if (this.savedRange && this.$refs.editor.contains(this.savedRange.commonAncestorContainer)) {
            selection.removeAllRanges(); selection.addRange(this.savedRange);
        }
    },
    insertNode(node) {
        this.restoreSelection();
        const selection = window.getSelection();
        if (selection?.rangeCount && this.$refs.editor.contains(selection.anchorNode)) {
            const range = selection.getRangeAt(0); range.deleteContents(); range.insertNode(node); range.setStartAfter(node); range.collapse(true);
            selection.removeAllRanges(); selection.addRange(range);
        } else this.$refs.editor.append(node);
        this.rememberSelection(); this.sync();
    },
    command(name, value = null) {
        this.restoreSelection();
        document.execCommand(name, false, value);
        this.rememberSelection();
        this.sync();
    },
    block(tag) { this.command('formatBlock', tag) },
    applyColor(color) {
        this.restoreSelection();
        const selection = window.getSelection();
        if (!selection?.rangeCount || selection.isCollapsed) return;
        const span = document.createElement('span'); span.dataset.textColor = color;
        try {
            const range = selection.getRangeAt(0); span.append(range.extractContents()); range.insertNode(span);
            range.setStartAfter(span); range.collapse(true); selection.removeAllRanges(); selection.addRange(range);
            this.rememberSelection(); this.sync();
        } catch (_) {}
    },
    addLink() {
        const url = window.prompt('Enter a link URL');
        if (url) this.command('createLink', url);
    },
    addImage() {
        const url = window.prompt('Enter an image URL (https://...)');
        if (!url || !/^https?:\/\//i.test(url)) return;
        const image = document.createElement('img'); image.src = url; image.alt = window.prompt('Describe the image (optional)') || ''; image.className = 'max-w-full rounded-lg';
        this.insertNode(image);
    },
    addTable() {
        const rows = Math.min(10, Math.max(1, Number(window.prompt('Number of rows', '3')) || 3));
        const columns = Math.min(8, Math.max(1, Number(window.prompt('Number of columns', '3')) || 3));
        const table = document.createElement('table'); const body = document.createElement('tbody');
        for (let row = 0; row < rows; row++) { const tr = document.createElement('tr'); for (let col = 0; col < columns; col++) { const cell = document.createElement(row === 0 ? 'th' : 'td'); cell.textContent = row === 0 ? 'Header' : 'Cell'; tr.append(cell); } body.append(tr); }
        table.append(body); this.insertNode(table);
    },
    addCodeBlock() { const pre = document.createElement('pre'); const code = document.createElement('code'); code.textContent = 'Write code here'; pre.append(code); this.insertNode(pre); },
    addEmoji(emoji) { this.command('insertText', emoji); this.menu = null; },
    addAttachment() { const url = window.prompt('Enter the attachment URL'); if (!url) return; const link = document.createElement('a'); link.href = url; link.textContent = window.prompt('Attachment name', 'Attachment') || 'Attachment'; this.insertNode(link); },
    runSlashCommand(command) { this.menu = null; if (command === 'table') this.addTable(); else if (command === 'image') this.addImage(); else if (command === 'code') this.addCodeBlock(); else if (command === 'emoji') this.menu = 'emoji'; else if (command === 'link') this.addLink(); },
    pasteText(event) {
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        this.rememberSelection();
        this.sync();
    }
}" {{ $attributes->class(['w-full overflow-hidden rounded-lg border border-slate-300 bg-white shadow-sm transition focus-within:border-indigo-600 focus-within:ring-4 focus-within:ring-indigo-600/10 dark:border-slate-700 dark:bg-slate-950']) }}>
    <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-800 dark:bg-slate-900" role="toolbar" aria-label="Text formatting">
        <select aria-label="Text style" title="Text style" x-on:mousedown="rememberSelection()" x-on:change="block($event.target.value)" class="h-8 max-w-28 rounded border-0 bg-transparent px-2 text-sm text-slate-600 focus:ring-1 focus:ring-indigo-500 dark:text-slate-300"><option value="p">Paragraph</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option><option value="blockquote">Quote</option><option value="pre">Code block</option></select>
        <button type="button" x-on:mousedown.prevent="command('bold')" aria-label="Bold" title="Bold" class="h-8 min-w-8 rounded px-2 text-sm font-bold text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">B</button>
        <button type="button" x-on:mousedown.prevent="command('italic')" aria-label="Italic" title="Italic" class="h-8 min-w-8 rounded px-2 text-sm italic text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">I</button>
        <button type="button" x-on:mousedown.prevent="command('underline')" aria-label="Underline" title="Underline" class="h-8 min-w-8 rounded px-2 text-sm underline text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">U</button>
        <button type="button" x-on:mousedown.prevent="command('insertUnorderedList')" aria-label="Bulleted list" title="Bulleted list" class="h-8 min-w-8 rounded px-2 text-sm text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">• List</button>
        <button type="button" x-on:mousedown.prevent="command('insertOrderedList')" aria-label="Numbered list" title="Numbered list" class="h-8 min-w-8 rounded px-2 text-sm text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">1. List</button>
        <select aria-label="Text color" title="Text color" x-on:mousedown="rememberSelection()" x-on:change="applyColor($event.target.value)" class="h-8 w-9 rounded border-0 bg-transparent px-1 text-slate-600 focus:ring-1 focus:ring-indigo-500 dark:text-slate-300"><option value="#334155">A</option><option value="#dc2626">Red</option><option value="#2563eb">Blue</option><option value="#16a34a">Green</option><option value="#9333ea">Purple</option></select>
        <button type="button" x-on:mousedown.prevent="addImage()" aria-label="Insert image" title="Insert image URL" class="h-8 min-w-8 rounded px-2 text-sm text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">▧</button>
        <button type="button" x-on:mousedown.prevent="addCodeBlock()" aria-label="Code block" title="Code block" class="h-8 min-w-8 rounded px-2 text-sm font-mono text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">&lt;/&gt;</button>
        <button type="button" x-on:mousedown.prevent="menu = menu === 'emoji' ? null : 'emoji'" aria-label="Insert emoji" title="Insert emoji" class="h-8 min-w-8 rounded px-2 text-sm text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">☺</button>
        <button type="button" x-on:mousedown.prevent="addAttachment()" aria-label="Attach link" title="Attach a file link" class="h-8 min-w-8 rounded px-2 text-sm text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">＋</button>
        <button type="button" x-on:mousedown.prevent="addLink()" aria-label="Insert link" title="Insert link" class="h-8 min-w-8 rounded px-2 text-sm text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">↗</button>
        <button type="button" x-on:mousedown.prevent="command('undo')" aria-label="Undo" title="Undo" class="h-8 min-w-8 rounded px-2 text-sm text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">↶</button>
        <button type="button" x-on:mousedown.prevent="command('redo')" aria-label="Redo" title="Redo" class="h-8 min-w-8 rounded px-2 text-sm text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">↷</button>
        <button type="button" x-on:mousedown.prevent="menu = menu === 'commands' ? null : 'commands'" aria-label="Insert content" title="Insert content" class="h-8 min-w-8 rounded px-2 text-sm font-semibold text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">/</button>
        <button type="button" x-on:mousedown.prevent="command('removeFormat')" aria-label="Clear formatting" title="Clear formatting" class="h-8 min-w-8 rounded px-2 text-xs font-medium text-slate-500 hover:bg-white dark:text-slate-400 dark:hover:bg-slate-800">Clear</button>
    </div>
    <div x-cloak x-show="menu === 'commands'" class="flex flex-wrap gap-1 border-b border-slate-200 px-3 py-2 dark:border-slate-800"><button type="button" x-on:click="runSlashCommand('table')" class="rounded px-2 py-1 text-xs hover:bg-slate-100 dark:hover:bg-slate-800">Table</button><button type="button" x-on:click="runSlashCommand('image')" class="rounded px-2 py-1 text-xs hover:bg-slate-100 dark:hover:bg-slate-800">Image</button><button type="button" x-on:click="runSlashCommand('code')" class="rounded px-2 py-1 text-xs hover:bg-slate-100 dark:hover:bg-slate-800">Code block</button><button type="button" x-on:click="runSlashCommand('emoji')" class="rounded px-2 py-1 text-xs hover:bg-slate-100 dark:hover:bg-slate-800">Emoji</button><button type="button" x-on:click="runSlashCommand('link')" class="rounded px-2 py-1 text-xs hover:bg-slate-100 dark:hover:bg-slate-800">Link</button></div>
    <div x-cloak x-show="menu === 'emoji'" class="flex flex-wrap gap-1 border-b border-slate-200 px-3 py-2 dark:border-slate-800"><template x-for="emoji in ['😀','😂','😍','👍','🎉','✅','🚀','👀','❤️','🙏']" :key="emoji"><button type="button" x-on:click="addEmoji(emoji)" x-text="emoji" class="rounded px-2 py-1 text-lg hover:bg-slate-100 dark:hover:bg-slate-800"></button></template></div>
    <div id="{{ $id }}" x-ref="editor" contenteditable="true" role="textbox" aria-label="{{ $label }}" aria-multiline="true" data-placeholder="{{ $placeholder }}" wire:ignore x-on:input="rememberSelection(); sync()" x-on:keydown="if ($event.key === '/') { $event.preventDefault(); menu = 'commands' }" x-on:keyup="rememberSelection()" x-on:mouseup="rememberSelection()" x-on:blur="sync()" x-on:paste="pasteText($event)" style="min-height: {{ $minHeight }}" class="rich-text-content px-3.5 py-3 text-sm leading-6 text-slate-800 outline-none dark:text-slate-200"></div>
</div>
