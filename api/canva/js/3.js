class NoteManager {
    constructor() {
        this.currentNoteId = null;// id  trxpntos xristi
        this.canvasId = document.querySelector('meta[name="current-canva-id"]').content;//id kamva pinaka
        this.userId = document.querySelector('meta[name="current-user-id"]').content;//id xristi
        this.lastUpdateTime = Date.now();// last time enimerosi
        
        // metavlites  gia drag drop  metakinisi antikeimenon
        this.isDragging = false;
        this.dragStartX = 0;
        this.dragStartY = 0;
        this.currentNoteElement = null;

        // metavlites gia epejerasia 
        this.editQuill = null; /// quiill editor
        this.originalNoteValues = {};// arxitis times simeiosis
        this.lastCursorUpdate = 0; // xroniki simansi gia to cursor ton xriston

        // arxikopoisi leitoyrgion
        this.initEventListeners();//event listerners
        this.initDraggableNotes();// metakinis simeoiseon
        this.startPolling(); // peridiosijos elegxos simeioseon 
        this.trackCursor(); /// parakoloythisi cursor xristi
        this.startCursorPolling(); //enimerosi ron cursor  ton  allon xriston
        this.loadNotePositions(); // forstosi ton simeioson
        this.initSidebarToggle(); // sibevar toggle
        this.initEditNoteQuill(); // quill editor  gi epejergasoa 
        
    }

    //arxikopoisi quill editr gia to noteediemodal
   initEditNoteQuill() {
    // fortosi toy dom
    setTimeout(() => {
        const editorElement = document.getElementById('editNotesEditor');
        const toolbarElement = document.getElementById('editToolbarContainer');
        
        if (editorElement && toolbarElement && !this.editQuill) {
            this.editQuill = new Quill('#editNotesEditor', {
                theme: 'snow',
                modules: {
                    toolbar: '#editToolbarContainer' // Απλή σύνταξη
                },
                placeholder: 'Γράψτε το περιεχόμενο της σημείωσής σας...'
            });
            
            console.log('Quill editor initialized successfully');
        } else {
            console.warn('Editor or toolbar element not found');
            console.log('Editor found:', !!editorElement);
            console.log('Toolbar found:', !!toolbarElement);
            console.log('Quill already initialized:', !!this.editQuill);
        }
    }, 1000); // megakli kathistresi fortosi dom
}
    

    // Sidebar toggle functionality 
    initSidebarToggle() {
        const sidebar = document.querySelector('.sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        
        // Toggle sidebar
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
        
        // Close sidebar when clicking overlay
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && 
                sidebar.classList.contains('active') &&
                !sidebar.contains(e.target) &&
                e.target !== sidebarToggle &&
                !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
        
        // Close sidebar when pressing escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
    }

    // fortosi simeioseon 
     async loadNotePositions() {
    try {
        const res = await fetch(`get_notes.php?canva_id=${this.canvasId}`);
        const result = await res.json();

        if (!result.success) {
            console.error("Σφάλμα από τον server:", result.error);
            return;
        }

        const notes = result.data;

        if (!Array.isArray(notes)) {
            console.error("Σφάλμα: Τα δεδομένα δεν είναι πίνακας!", notes);
            return;
        }
        //enimerosi ton eidopoiosen ps to kampanaki st sytma mas 

       
        if (typeof this.updateNotifUI === 'function') {
            this.updateNotifUI(notes);
        }

        //elexfoa gia pristomesims deadlines simera osona anaora gia tis isimeisoeisn
        
     
        const todayStr = new Date().toLocaleDateString('en-CA'); // paineri to yyyymmmddd
        
        
        let notesDueToday = [];

        notes.forEach(note => {
            ///elegxos immerominians
          
            if (note.due_date && note.due_date === todayStr) {
                notesDueToday.push(note.tag || "Σημείωση χωρίς ετικέτα");
            }

            // enimerosi ti thesis ton simeioseosn pano sto pinaak-kamva
            
            
            const noteEl = document.querySelector(`.note-container[data-note-id="${note.note_id}"]`);
            if (noteEl) {
                noteEl.style.left = `${note.position_x}px`;
                noteEl.style.top = `${note.position_y}px`;
                
                // ebimerois pwiroeomenoy syxrnimsos
                const contentEl = noteEl.querySelector('.ql-editor');
                if (contentEl && note.content) {
                    contentEl.innerHTML = note.content;
                }
            }
        });

        // apostoli browser notifiactation mono maia fora ana foetous
       
        if (notesDueToday.length > 0 && !this.notificationShown) {
            this.sendBrowserNotification(notesDueToday);
            this.notificationShown = true; 
        }

    } catch (error) {
        console.error("Σφάλμα κατά το fetch ή το JSON parsing:", error);
    }
}
    checkAllDeadlines(notes) {
        const now = new Date();
        let overdueCount = 0;
        let upcomingCount = 0;
        const oneDayInMs = 24 * 60 * 60 * 1000;

        notes.forEach(note => {
            if (note.due_date) {
                const dueDate = new Date(note.due_date);
                const timeDiff = dueDate - now;

                if (timeDiff < 0) {
                    overdueCount++; // ligmenies simeiosei s
                } else if (timeDiff < oneDayInMs) {
                    upcomingCount++; // ligoyn se ligotero apo 24 ores
                }
            }
        });

        // emfnisisi omadimoiimenoy minimatos
        if (overdueCount > 0 || upcomingCount > 0) {
            this.showGroupedAlert(overdueCount, upcomingCount);
        }
    }
    showGroupedAlert(overdue, upcoming) {
        const toastEl = document.getElementById('deadlineToast');
        const toastBody = document.getElementById('deadlineToastBody');
        
        if (toastEl && toastBody) {
            let message = "";
            if (overdue > 0) {
                message += `<div class="text-danger"><strong><i class="bi bi-x-circle-fill"></i> ${overdue}</strong> σημειώσεις έχουν λήξει!</div>`;
            }
            if (upcoming > 0) {
                message += `<div class="text-warning"><strong><i class="bi bi-exclamation-triangle-fill"></i> ${upcoming}</strong> σημειώσεις λήγουν σύντομα (εντός 24ωρου).</div>`;
            }
            
            toastBody.innerHTML = message + `<hr><small>Κάντε αναζήτηση για να τις εντοπίσετε.</small>`;
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        }
    }


    // elegxos gia neimeroseis
    startPolling() {
        setInterval(() => this.fetchUpdates(), 3000);//apostelei tin thesi toy cursora ston server kathe 100ms
    } 

    // parakaoloythisi tis  kinisis toy potntikouo cusror xristi sto notesboard perixomeno toy pinaka
    
    trackCursor() {
        document.getElementById('notesBoard').addEventListener('mousemove', async (e) => {
            const rect = e.currentTarget.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            if (Date.now() - this.lastCursorUpdate < 100) return;
            this.lastCursorUpdate = Date.now();

            await fetch('update_cursor.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    user_id: this.userId, 
                    canva_id: this.canvasId, 
                    x, 
                    y 
                })
            });
        });
    }

    // peridsoiki enimerosi theneson cursor allon xriston
     
    startCursorPolling() {
        setInterval(async () => {
            try {
                const res = await fetch(`get_cursors.php?canva_id=${this.canvasId}&user_id=${this.userId}`);
                const data = await res.json();
                
                // diagradi palion cursor
                document.querySelectorAll('.remote-cursor').forEach(el => {
                    if (!el.dataset.lastUpdate || Date.now() - parseInt(el.dataset.lastUpdate) > 2000) {
                        el.remove();
                    }
                });

                //enimerosi kai dimoyrsia neon cursor
                                data.cursors.forEach(cursor => {
                    if (cursor.user_id !== this.userId) {
                        let cursorEl = document.querySelector(`.remote-cursor[data-user-id="${cursor.user_id}"]`);
                        
                        if (!cursorEl) {
                            cursorEl = document.createElement('div');
                            cursorEl.className = 'remote-cursor';
                            cursorEl.dataset.userId = cursor.user_id;
                            cursorEl.innerHTML = `
                                <div class="cursor-arrow">👆</div>
                                <div class="cursor-name">${cursor.username || 'Χρήστης'}</div>
                            `;
                            document.getElementById('notesBoard').appendChild(cursorEl);
                        }
                        
                        // Ενημέρωση θέσης
                        cursorEl.style.left = `${cursor.x}px`;
                        cursorEl.style.top = `${cursor.y}px`;
                        cursorEl.dataset.lastUpdate = Date.now();
                    }
                });
            } catch (error) {
                console.error('Cursor polling error:', error);
            }
        }, 500);//anaktisi ton thesio cusror ton allon xriston ana kathe 500ms
    }

    // Ανάκτηση ενημερώσεων από τον server
   async fetchUpdates() {
        try {
            const res = await fetch(`fetch_updates.php?canva_id=${this.canvasId}&last_update=${this.lastUpdateTime}&user_id=${this.userId}`);
            
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            
            const data = await res.json();
            
            if (!data.success) {
                console.error("Σφάλμα από τον server:", data.error);
                return;
            }

            const serverNoteIds = data.notes ? data.notes.map(note => note.note_id.toString()) : [];

            // diafrafi simeioseon paoy exoyn afairethei apotoyw alloys xristes
            document.querySelectorAll('.note-container').forEach(noteEl => {
                if (!serverNoteIds.includes(noteEl.dataset.noteId)) {
                    noteEl.remove();
                }
            });

            //enimerosi  ton theseon yparxon simeosesn kai prostkki neo simeioson meso to addnotetocanvas()
                 
           data.notes.forEach(note => {
    const existing = document.querySelector(`.note-container[data-note-id="${note.note_id}"]`);
    
    if (existing) {
        // enimerosi
        existing.style.left = `${note.position_x}px`;
        existing.style.top = `${note.position_y}px`;

        // enimerosi perixeoeno
        const contentEl = existing.querySelector('.ql-editor');
        if (contentEl && contentEl.innerHTML !== note.content) {
            contentEl.innerHTML = note.content;
        }

        // enimerosi clolor font
        existing.style.backgroundColor = note.color;
        existing.style.fontFamily = note.font;

        // enimerosi tag
        const badgeGroup = existing.querySelector('.badge-group');
        if (badgeGroup && note.tag) {
            badgeGroup.innerHTML = `<span class="badge bg-dark">${note.tag}</span>`;
        }

        // enimerosi lock loacarisma 
        this.updateNoteLockStatus(existing, note);
    } else {
        this.addNoteToCanvas(note);
    }
});

            this.lastUpdateTime = Date.now();

        } catch (error) {
            console.error("Σφάλμα κατά το fetch updates:", error);
        }
    }
    // enimeros tis katastasei simeioaseis an einia kleidomendi
    updateNoteLockStatus(noteElement, noteData) {
        if (noteData.locked_by) {
            noteElement.dataset.lockedBy = noteData.locked_by;
            if (!noteElement.querySelector('.lock-indicator')) {
                const lockEl = document.createElement('div');
                lockEl.className = 'lock-indicator';
                lockEl.innerHTML = `🔒 ${noteData.locked_by_name || 'Κλειδωμένο'}`;
                noteElement.prepend(lockEl);
            }
        } else {
            noteElement.dataset.lockedBy = '';
            const lockEl = noteElement.querySelector('.lock-indicator');
            if (lockEl) lockEl.remove();
        }
    } 

  //emfnisi tis simeioseis meta to create add
    addNoteToCanvas(note) {
    const notesBoard = document.getElementById('notesBoard');
    if (!notesBoard) return;

    const noteEl = document.createElement('div');
    noteEl.className = 'note-container';
    
    // edamrofi styl simeioseis
    noteEl.style.backgroundColor = note.color || '#ffffff';
    noteEl.style.left = `${note.position_x || 100}px`;
    noteEl.style.top = `${note.position_y || 100}px`;
    noteEl.style.fontFamily = note.font || 'Arial';
    
    // Data attributes
    noteEl.dataset.noteId = note.note_id || note.id;
    noteEl.dataset.lockedBy = note.locked_by || '';

    // logiki to lock simeioseis locked_by (PHP: if (!empty($note['locked_by'])))
    let lockHtml = '';
    if (note.locked_by) {
        const lockColor = (note.locked_by == 1) ? '#ff0000' : '#0000ff';
        const lockName = note.locked_by_name || 'Κλειδωμένο';
        lockHtml = `
            <div class="lock-indicator" 
                 style="position: absolute; top: -12px; right: 5px; background: #ffc107; 
                        padding: 2px 5px; border-radius: 2px; font-size: 15px; color: ${lockColor}; z-index: 10;">
                🔒 ${lockName}
            </div>`;
    }

    // Toolbar & Badges (PHP: note-toolbar)
    const tagHtml = note.tag ? `<span class="badge bg-dark">${note.tag}</span>` : '';
    const iconHtml = note.icon ? `<i class="bi bi-${note.icon} float-end fs-5 mb-2"></i>` : '';
    
    // imeromijinia (PHP: date('d/m/Y', strtotime(...)))
    let dateHtml = '';
    if (note.due_date) {
        const d = new Date(note.due_date);
        const formattedDate = d.toLocaleDateString('el-GR');
        dateHtml = `<div class="mt-3 small text-muted">Προθεσμία: ${formattedDate}</div>`;
    }

    // 
    noteEl.innerHTML = `
        ${lockHtml}
        <div class="note-content">
            <div class="note-toolbar d-flex justify-content-between align-items-center mb-2">
                <div class="badge-group">
                    ${tagHtml}
                </div>
                <div class="btn-group">
                    <button class="btn btn-sm btn-light edit-btn">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-sm btn-danger delete-btn">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            
            ${iconHtml}
            
            <div class="ql-editor border-top">
                ${note.content}
            </div>
            
            ${dateHtml}
        </div>
    `;

    notesBoard.appendChild(noteEl);
    //symdesi me toy enevnt listeners
    const editBtn = noteEl.querySelector('.edit-btn');
    const deleteBtn = noteEl.querySelector('.delete-btn');
    if(editBtn)
    {
         editBtn.onclick = (e) => { 
        e.stopPropagation(); 
        this.editNote(noteEl); // Περνάμε το στοιχείο
    };
    }

    if (deleteBtn) {
    deleteBtn.onclick = (e) => { 
        e.stopPropagation(); 
        // ΔΙΟΡΘΩΣΗ: Περνάμε το noteEl, όχι το noteEl.dataset.noteId
        this.deleteNote(noteEl); 
    };
}
    // Ενεργοποίηση Dragging
    this.initDraggableNotes();
}

    // Αρχικοποίηση event listeners
    initEventListeners()
    {
        // Αποθήκευση σημείωσης
        document.getElementById('saveNote').addEventListener('click', () => this.saveNote());
        
        // Επεξεργασία σημείωσης
        document.getElementById('notesBoard').addEventListener('click', (e) => {
            
            document.getElementById('notesBoard').addEventListener('click', (e) => {
        const editBtn = e.target.closest('.edit-btn');
        if (editBtn) {
            e.stopPropagation();
            const noteElement = editBtn.closest('.note-container');
            this.editNote(noteElement); // Κάλεσε τη συνάρτηση που ανοίγει το Modal
        }
        
    });
        });

        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        document.getElementById('notesBoard').addEventListener('click', async (e) => {
            


         const el = e.target.closest('.note-container');
         if (!el) return;
    
         const noteId = el.dataset.noteId;
         const userRole = document.body.dataset.userRole; // rolo apo to 11.php metatagas
         let url = 'lock_note.php';
         if (userRole === 'admin'){
               url += '?admin=1';//admin
            
         }
         try
         {
            
        const response = await fetch(url, 
            {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ note_id: noteId })
        });
        
        const result = await response.json();
        
        // to 423 code simineia toi i php den to anagaotisei to systima os admin      
       
        if (response.status === 423) {
            Swal.fire({
                icon: 'info',
                title: 'Κλειδωμένο',
                text: `Η σημείωση είναι κλειδωμένη από τον χρήστη ${result.locked_by_name}`
            });
        }
        
        
    } catch (error) {
        console.error('Σφάλμα:', error);
    }
});
        
    document.getElementById('notesBoard').addEventListener('click', async (e) => {
    const mediaEl = e.target.closest('.media-item');
    if (!mediaEl) return;
    
    const mediaId = mediaEl.dataset.id;
    const canvaId = this.canvasId;
    const userId = this.userId;
    
    // enimerosi apotsiki     admin=1 αν ο χρήστης είναι admin
    const isAdminQuery = document.body.dataset.userRole === 'admin' ? '?admin=1' : '';
    
    try {
        const response = await fetch(`lock_media.php${isAdminQuery}`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ 
                media_id: mediaId, 
                canva_id: canvaId,
                user_id: userId
            })
        });
        const result = await response.json();
        
        // adn to systima mas dei oti anagnarozei to admini to backend tha epistrecei success trus akoma einia kleidoemenop
        
        if (result.error === 'Κλειδωμένο') {
            Swal.fire({
                icon: 'warning',
                title: 'Κλειδωμένο',
                text: `Αυτό το πολυμέσο είναι κλειδωμένο από τον χρήστη ${result.locked_by_name}`,
                timer: 2000
            });
        } else if (result.success) {
            console.log("Media access granted (Admin or Lock acquired)");
            mediaEl.dataset.lockedBy = userId;
            if (!mediaEl.querySelector('.lock-indicator')) {
                const lockEl = document.createElement('div');
                lockEl.className = 'lock-indicator';
                //an enia admin kai to kledieomna itna alloy mporoyme na to deijoeyme
                
                lockEl.innerHTML = `<i class="bi bi-lock"></i> ${document.body.dataset.userRole === 'admin' ? 'Admin Access' : 'Κλειδωμένο από εσάς'}`;
                mediaEl.prepend(lockEl);
            }
        }
    } catch (error) {
        console.error('Σφάλμα κλειδώματος:', error);
    }
});

        //delete note
        document.getElementById('notesBoard').addEventListener('click', (e) => {
            if (e.target.closest('.delete-btn')) {
                const noteElement = e.target.closest('.note-container');
                this.deleteNote(noteElement);
            }
        });

        // noteeditform
        document.getElementById('editNoteForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.updateNote(e);
        });

        // Dark mode
        document.querySelector('.switch input').addEventListener('change', function(e) {
            document.body.classList.toggle('dark', e.target.checked);
            localStorage.setItem('darkMode', e.target.checked);
        });

        // Restore dark mode on load
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark');
            document.querySelector('.switch input').checked = true;
        }

        // export
        document.getElementById("exportAsImage").addEventListener("click", this.exportAsImage);
        document.getElementById("exportAsPDF").addEventListener("click", this.exportAsPDF);
        document.getElementById("exportAsText").addEventListener("click", this.exportAsText);
        document.getElementById("exportWordBtn").addEventListener("click", this.exportAsWord);

        // Zoom
        document.getElementById("zoomIn").addEventListener("click", this.zoomIn);
        document.getElementById("zoomOut").addEventListener("click", this.zoomOut);

        // create new canvases
        document.getElementById('createCanvasBtn').addEventListener('click', () => this.createCanvas());

        // search pinakons
        document.getElementById('searchCanvases').addEventListener('input', this.searchCanvases);

        // Event listeners gia create pinaka delete ka
        // 
      
        document.getElementById('createCanvasBtn').addEventListener('click', async () => {
            const canvasName = document.getElementById('canvasName').value.trim();
            const canvasCategory = document.getElementById('canvasCategory').value;
            const canvasAccess = document.getElementById('canvasAccess').value;

            if (!canvasName) {
                Swal.fire('Σφάλμα', 'Παρακαλώ εισάγετε ένα όνομα πίνακα', 'error');
                return;
            }

            try {
                const response = await fetch('create_canva.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name: canvasName,
                        category: canvasCategory,
                        access: canvasAccess
                    })
                });

                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.error || 'Αποτυχία δημιουργίας');
                }

                if (result.success) {
                    Swal.fire({
                        title: 'Επιτυχία!',
                        text: 'Ο πίνακας δημιουργήθηκε με επιτυχία',
                        icon: 'success',
                        showConfirmButton: true,
                        willClose: () => {
                            window.location.href = `11.php?id=${result.canva_id}`;
                        }
                    });
                } else {
                    throw new Error(result.error || 'Αποτυχία δημιουργίας');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Σφάλμα',
                    text: error.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
        
        
        
        document.getElementById('searchCanvases').addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('#canvasesList li').forEach(li => {
                const name = li.textContent.toLowerCase();
                li.style.display = name.includes(term) ? 'block' : 'none';
            });
        });

        document.querySelectorAll('.delete-canvas').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const canvasId = btn.dataset.id;

                const confirmed = await Swal.fire({
                    title: 'Διαγραφή πίνακα',
                    text: 'Θα διαγραφούν όλες οι σημειώσεις του πίνακα. Είστε σίγουροι;',
                    icon: 'warning',
                    showCancelButton: true
                });

                if (confirmed.isConfirmed) {
                    const response = await fetch(`delete_canva.php?id=${canvasId}`, { method: 'DELETE' });

                    if (response.ok) {
                        btn.closest('li').remove();
                        Swal.fire('Επιτυχία', 'Ο πίνακας διαγράφηκε', 'success');
                    }
                }
            });
        });

        document.querySelectorAll('.edit-name').forEach(button => {
            button.addEventListener('click', () => {
                const li = button.closest('li');
                const link = li.querySelector('.canvas-link');
                const originalText = link.textContent;
                const canvasId = button.dataset.id;

                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm';
                input.value = originalText;

                link.replaceWith(input);
                input.focus();

                input.addEventListener('blur', async () => {
                    const newName = input.value.trim();

                    if (newName && newName !== originalText) {
                        const response = await fetch(`rename_canva.php?id=${canvasId}&name=${encodeURIComponent(newName)}`);
                        if (response.ok) {
                            const newLink = document.createElement('a');
                            newLink.href = `11.php?id=${canvasId}`;
                            newLink.textContent = newName;
                            newLink.className = 'canvas-link me-2 flex-grow-1';
                            input.replaceWith(newLink);
                        } else {
                            alert('Η αλλαγή ονόματος απέτυχε.');
                            input.replaceWith(link);
                        }
                    } else {
                        input.replaceWith(link);
                    }
                });

                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        input.blur();
                    }
                });
            });
        });

    document.getElementById('addCollaboratorForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const canvaId = document.querySelector('meta[name="current-canva-id"]').content;
    
    try {
        const response = await fetch('add_collaborator.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                canva_id: canvaId,
                email: formData.get('email'),
                permission: formData.get('permission')
            })
        });
        
        const result = await response.json();
        
        if (!response.ok) {
          
            if (result.error_code === 'already_exists') {
           
                Swal.fire({
                    icon: 'info',  
                    title: 'Πληροφορία',
                    text: result.error || 'Ο χρήστης είναι ήδη συνεργάτης σε αυτόν τον πίνακα'
                });
                return; 
            } else {
                throw new Error(result.error || 'Αποτυχία προσθήκης συνεργάτη');
            }
        }
        
        if (!result.success) {
            throw new Error(result.error || 'Αποτυχία προσθήκης συνεργάτη');
        }
        
        Swal.fire({
            icon: 'success',
            title: 'Επιτυχία!',
            text: 'Ο συνεργάτης προστέθηκε με επιτυχία.',
            timer: 2000
        });
        
        form.reset();
        $('#addCollaboratorModal').modal('hide');
        window.location.reload();
    } catch (error) {
     
        Swal.fire({
            icon: 'error',
            title: 'Σφάλμα',
            text: error.message
        });
        console.error('Σφάλμα προσθήκης συνεργάτη:', error);
    }
});

        document.querySelectorAll('.remove-collaborator').forEach(btn => {
            btn.addEventListener('click', async () => {
                const userId = btn.dataset.userId;
                const canvaId = document.querySelector('meta[name="current-canva-id"]').content;
                
                const confirmed = await Swal.fire({
                    title: 'Είστε σίγουρος;',
                    text: "Θα αφαιρέσετε αυτόν τον συνεργάτη από τον πίνακα",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Ναι, αφαίρεση!',
                    cancelButtonText: 'Ακύρωση'
                });

                if (!confirmed.isConfirmed) return;

                try {
                    const response = await fetch('remove_collaborator.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            canva_id: canvaId,
                            user_id: userId
                        })
                    });

                    if (!response.ok) throw new Error('Αποτυχία αφαίρεσης συνεργάτη');

                    Swal.fire({
                        icon: 'success',
                        title: 'Επιτυχία!',
                        text: 'Ο συνεργάτης αφαιρέθηκε',
                        timer: 1500
                    });

                    window.location.reload();
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Σφάλμα',
                        text: error.message
                    });
                    console.error('Σφάλμα αφαίρεσης συνεργάτη:', error);
                }
            });
        });

        // delete media
        document.addEventListener('click', async (e) => {
            if (e.target.closest('.delete-media')) {
                const mediaId = e.target.closest('.delete-media').dataset.id;

                const confirmed = await Swal.fire({
                    title: 'Διαγραφή Πολυμέσου',
                    text: 'Θέλετε να διαγράψετε αυτό το πολυμέσο;',
                    icon: 'warning',
                    showCancelButton: true
                });

                if (confirmed.isConfirmed) {
                    try {
                        const response = await fetch(`delete_media.php?id=${mediaId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        
                        if (response.ok) {
                            e.target.closest('.media-item').remove();
                            Swal.fire('Επιτυχία!', 'Το πολυμέσο διαγράφηκε.', 'success');
                        }
                    } catch (error) {
                        Swal.fire('Σφάλμα', 'Η διαγραφή απέτυχε.', 'error');
                    }
                }
            }
        });
    }
    // synartis gia lockarimsi tis simeioseis
    
addNoteLockIndicator(noteElement, userId, lockedByName) {
    noteElement.dataset.lockedBy = userId;
    
    // afairesei yparxontsos lock 
    const existingLock = noteElement.querySelector('.lock-indicator');
    if (existingLock) existingLock.remove();
    
    // create neou
   
    const lockEl = document.createElement('div');
    lockEl.className = 'lock-indicator';
    lockEl.style.cssText = 'position: absolute; top: -12px; right: 5px; background: #ffc107; padding: 2px 5px; border-radius: 2px; font-size: 15px;';
    lockEl.innerHTML = `🔒 ${lockedByName || 'Κλειδωμένο από εσάς'}`;
    noteElement.prepend(lockEl);
}

    
    //edit notew
    
   
   async editNote(noteElement) {
    const noteId = noteElement.dataset.noteId;
    //role apo to 11.php pokeumenoy na exie prosrasei sto systima o admin mas /metatags
  
    const userRole = document.body.dataset.userRole; 
    
    try {
        //add admin ostw na jerei an o admin apo tin php gia na toy dose connect sto systima
      
        const isAdminParam = userRole === 'admin' ? '&admin=1' : '';
        const response = await fetch(`get_note.php?canva_id=${this.canvasId}&note_id=${noteId}${isAdminParam}`);
        
        const result = await response.json();

        //elgxos an i php epestrece sfalma px an den edei dikaioma 
       
        if (!response.ok) throw new Error(result.error || 'Αποτυχία φόρτωσης σημείωσης');

        // epistrofi toy antikeimeno ti simeioseis apo to php
        const note = result; 

        document.getElementById('editNoteId').value = noteId;
        
        //content          Quill editor
        this.editQuill.root.innerHTML = note.content || '';
        
        // ypoloipa pedia toy modal
     
        document.getElementById('editNoteTag').value = note.tag || '';
        document.getElementById('editNoteIcon').value = note.icon || '';
        document.getElementById('editNoteFont').value = note.font || 'Arial';
        document.getElementById('editNoteDueDate').value = note.due_date || '';
        document.getElementById('editNoteColor').value = note.color || '#ffffff';
        //save ton timosi gia elgxo allagon 

     
        this.originalNoteValues = {
            content: note.content || '',
            tag: note.tag || '',
            icon: note.icon || '',
            font: note.font || 'Arial',
            due_date: note.due_date || '',
            color: note.color || '#ffffff'
        };
        
        //emfanisi modal
        $('#editNoteModal').modal('show');
        
    } catch (error) {
        Swal.fire({ 
            icon: 'error', 
            title: 'Πρόσβαση Αρνήθηκε', 
            text: 'Μόνο ο ιδιοκτήτης της σημείωσης, ο Teacher ή ο Admin μπορούν να την επεξεργαστούν.' 
        });
        console.error('Σφάλμα φόρτωσης σημείωσης:', error);
    }
}

    //updatenote
    async updateNote(e) {
        const formData = new FormData(e.target);
        const noteId = formData.get('note_id');
        const currentContent = this.editQuill.root.innerHTML;
        
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!csrfToken) {
                console.error('CSRF token not found');
                return;
            }

          
            const updateData = { note_id: noteId };
            let hasChanges = false;

            if (currentContent !== this.originalNoteValues.content) {
                updateData.content = currentContent;
                hasChanges = true;
            }
            
            const currentValues = {
                tag: formData.get('tag'),
                icon: formData.get('icon'),
                font: formData.get('font'),
                due_date: formData.get('due_date'),
                color: formData.get('color')
            };
            
            Object.keys(currentValues).forEach(key => {
                if (currentValues[key] !== this.originalNoteValues[key]) {
                    updateData[key] = currentValues[key];
                    hasChanges = true;
                }
            });

            if (!hasChanges) {
                Swal.fire({ icon: 'info', title: 'Δεν υπάρχουν αλλαγές', text: 'Δεν πραγματοποιήσατε καμία αλλαγή' });
                return;
            }

            const response = await fetch('update_note.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(updateData)
            });
            
            const result = await response.json();
            
            if (!response.ok || !result.success) {
                throw new Error(result.error || 'Αποτυχία ενημέρωσης σημείωσης');
            }
            
            Swal.fire({ icon: 'success', title: 'Επιτυχία!', text: 'Η σημείωση ενημερώθηκε.', timer: 1500 });
            $('#editNoteModal').modal('hide');
            window.location.reload();
            
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Σφάλμα', text: error.message });
            console.error('Σφάλμα ενημέρωσης σημείωσης:', error);
        }
    }

  async deleteNote(noteElement) {
    const noteId = noteElement.dataset.noteId;

    //  admin για το delete_note.php
    const isAdminParam = document.body.dataset.userRole === 'admin' ? '&admin=1' : '';
    
   
    const confirmed = await Swal.fire({
        title: 'Είστε σίγουρος;',
        text: "Η ενέργεια αυτή δεν αναιρείται!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ναι, διαγραφή!',
        cancelButtonText: 'Ακύρωση'
    });

    if (!confirmed.isConfirmed) return;

    try {
        //   admin στο URL
        const response = await fetch(`delete_note.php?id=${noteId}${isAdminParam}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const result = await response.json();

        if (response.ok && result.success) {
            noteElement.style.transition = 'all 0.3s ease';
            noteElement.style.transform = 'scale(0)';
            setTimeout(() => {
                noteElement.remove();
                Swal.fire('Διαγράφηκε!', 'Η σημείωση αφαιρέθηκε με επιτυχία.', 'success');
            }, 300);
            
            this.lastUpdateTime = Date.now();
        } else {
            // emfanisi sfalmatos apo tin php px klisimo apo allon 
          
            Swal.fire('Αποτυχία', result.error || 'Η διαγραφή απέτυχε.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Σφάλμα', 'Υπήρξε πρόβλημα στην επικοινωνία με τον διακομιστή.', 'error');
    }
}


    // Drag & drop functionality metakinis simeiooseon 
    initDraggableNotes() {
        interact('.note-container').draggable({
            inertia: true,
            modifiers: [
                interact.modifiers.restrictRect({
                    restriction: 'parent',
                    endOnly: true
                })
            ],
            autoScroll: true,
            listeners: {
                start: (event) => {
                    this.currentNoteElement = event.target;
                    event.target.classList.add('dragging');
                    event.target.style.opacity = '0.8';
                },
                move: (event) => {
                    const target = event.target;
                    const x = (parseFloat(target.style.left) || 0) + event.dx;
                    const y = (parseFloat(target.style.top) || 0) + event.dy;
                    
                    target.style.left = `${x}px`;
                    target.style.top = `${y}px`;
                },
                end: (event) => {
                    event.target.classList.remove('dragging');
                    event.target.style.opacity = '1';
                    this.saveNotePosition(event.target);
                }
            }
        });
    }
    

    // save thesis notes
    async saveNotePosition(noteElement) {
        try {
            const response = await fetch('save_position.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    note_id: noteElement.dataset.noteId,
                    position_x: parseInt(noteElement.style.left),
                    position_y: parseInt(noteElement.style.top)
                })
            });

            if (!response.ok) throw new Error('Αποτυχία ενημέρωσης θέσης');
        } catch (error) {
            console.error('Σφάλμα ενημέρωσης θέσης:', error);
        }
    }

    //  (export, zoom, etc.)
    exportAsImage() {
        html2canvas(document.querySelector("#notesBoard")).then(canvas => {
            const link = document.createElement("a");
            link.download = "notes.png";
            link.href = canvas.toDataURL("image/png");
            link.click();
        });
    }

    exportAsPDF() {
    // Show loading
    Swal.fire({
        title: 'Δημιουργία PDF',
        text: 'Παρακαλώ περιμένετε...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const notesBoard = document.querySelector("#notesBoard");
    
    html2canvas(notesBoard, {
        scale: 2, // Higher quality
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false, // Disable console logging for better performance
        onclone: function(clonedDoc) {
            // Optional: Style adjustments for PDF export
            const clonedBoard = clonedDoc.querySelector("#notesBoard");
            if (clonedBoard) {
                clonedBoard.style.backgroundColor = 'white';
                clonedBoard.style.padding = '20px';
            }
        }
    }).then(canvas => {
        const imgData = canvas.toDataURL("image/png", 1.0);
        const pdf = new jspdf.jsPDF({
            orientation: canvas.width > canvas.height ? 'landscape' : 'portrait',
            unit: 'px',
            format: [canvas.width, canvas.height]
        });

        // Calculate dimensions to fit the entire canvas
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = pdf.internal.pageSize.getHeight();
        
        // Calculate aspect ratio
        const aspectRatio = canvas.width / canvas.height;
        let imgWidth = pdfWidth;
        let imgHeight = pdfWidth / aspectRatio;
        
        // If image height exceeds PDF height, adjust based on height
        if (imgHeight > pdfHeight) {
            imgHeight = pdfHeight;
            imgWidth = pdfHeight * aspectRatio;
        }

        // Center the image on the page
        const x = (pdfWidth - imgWidth) / 2;
        const y = (pdfHeight - imgHeight) / 2;

        pdf.addImage(imgData, "PNG", x, y, imgWidth, imgHeight);
        pdf.save("notes.pdf");
        
        Swal.fire({
            icon: 'success',
            title: 'Επιτυχία!',
            text: 'Το PDF δημιουργήθηκε και κατέβηκε.',
            timer: 2000
        });
    }).catch(error => {
        console.error('PDF export error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Σφάλμα',
            text: 'Αποτυχία δημιουργίας PDF: ' + error.message
        });
    });
}

    exportAsText() {
        //create title gi to arxeio mas 
   
    let cleanContent = "--- ΕΞΑΓΩΓΗ ΣΗΜΕΙΩΣΕΩΝ ΣΥΝΕΡΓΑΤΙΚΟΥ ΠΙΝΑΚΑ ---\n";
    cleanContent += "Ημερομηνία: " + new Date().toLocaleString() + "\n";
    cleanContent += "------------------------------------------\n\n";

    //epilogi gia ta contaienrs ton simeoseion
     
    const notes = document.querySelectorAll('.note-container');

    if (notes.length === 0) {
        alert("Δεν υπάρχουν σημειώσεις για εξαγωγή!");
        return;
    }

    notes.forEach((note, index) => {
        //note content kai to note tag an yparxei
        // Παίρνουμε μόνο το κείμενο της σημείωσης (note-content) 
        
        const noteText = note.querySelector('.note-content')?.innerText.trim() || "Κενή σημείωση";
        const noteTag = note.querySelector('.note-tag')?.innerText.trim() || "Χωρίς Tag";
        const author = note.querySelector('.locked-by-name')?.innerText.trim() || "Άγνωστος";

        cleanContent += `ΣΗΜΕΙΩΣΗ #${index + 1}\n`;
        cleanContent += `Ετικέτα: ${noteTag}\n`;
        cleanContent += `Δημιουργός/Κλείδωμα: ${author}\n`;
        cleanContent += `Περιεχόμενο: ${noteText}\n`;
        cleanContent += "------------------------------------------\n\n";
    });

    //downaloda file
    const blob = new Blob([cleanContent], { type: "text/plain;charset=utf-8" });
    const link = document.createElement("a");
    link.download = "καθαρές_σημειώσεις.txt";
    link.href = URL.createObjectURL(blob);
    link.click();
    
   
    Swal.fire('Επιτυχία!', 'Το καθαρό αρχείο .txt δημιουργήθηκε.', 'success');
}
        exportAsWord() {
            const canvaId = document.querySelector('meta[name="current-canva-id"]').content;
            
            const downloadUrl = `export_word.php?canva_id=${canvaId}`;
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.target = '_blank';
            link.click();
            
            Swal.fire({
                icon: 'success',
                title: 'Εξαγωγή σε Word',
                text: 'Η εξαγωγή ξεκίνησε. Το αρχείο θα κατέβει αυτόματα.',
                timer: 3000
            });
        }

        zoomIn() {
            const board = document.getElementById("notesBoard");
            let currentZoom = parseFloat(board.style.transform?.match(/scale\(([^)]+)\)/)?.[1] || 1);
            currentZoom += 0.1;
            board.style.transform = `scale(${currentZoom})`;
            board.style.transformOrigin = 'top left';
        }

        zoomOut() {
            const board = document.getElementById("notesBoard");
            let currentZoom = parseFloat(board.style.transform?.match(/scale\(([^)]+)\)/)?.[1] || 1);
            currentZoom = Math.max(0.2, currentZoom - 0.1);
            board.style.transform = `scale(${currentZoom})`;
            board.style.transformOrigin = 'top left';
        }

        createCanvas() {
           //create canvas
            console.log('Creating new canvas...');
        }

        searchCanvases() {
          
            console.log('Searching canvases...');
        }
        //eidopoiso apo to kampanik simeisoesos 

         updateNotifUI(notes) {
        const today = new Date().toISOString().split('T')[0];
        const urgentNotes = notes.filter(n => n.due_date && n.due_date <= today);
        
        const countBadge = document.getElementById('notif-count');
        const contentBox = document.getElementById('notif-content');

        if (urgentNotes.length > 0) {
            countBadge.innerText = urgentNotes.length;
            countBadge.classList.remove('d-none');

            let html = "";
            urgentNotes.forEach(note => {
                const isOverdue = note.due_date < today;
                html += `
                    <li class="p-2 border-bottom" style="cursor:pointer" onclick="window.focusNote('${note.note_id}')">
                        <div class="d-flex align-items-center">
                            <span class="me-2 fs-4">${note.icon || '📌'}</span>
                            <div>
                                <div class="fw-bold small">${note.tag || 'Σημείωση'}</div>
                                <div class="text-${isOverdue ? 'danger' : 'warning'} smaller">
                                    ${isOverdue ? 'Έληξε' : 'Λήγει σήμερα'}
                                </div>
                            </div>
                        </div>
                    </li>`;
            });
            contentBox.innerHTML = html;
        } else {
            countBadge.classList.add('d-none');
            contentBox.innerHTML = '<li class="p-3 text-center text-muted small">Όλα έτοιμα! 🎉</li>';
        }
    }
   
    }

   class MediaManager {
    constructor() {
        this.richTextQuill = null;//quoll gia rich notes
        this.editNoteQuill = null;//quuoll gia epkergasia

        this.currentCanvasId = null; // id trexontos kamva pinaka
        this.currentUserId = null; // id trexontos xristi

        // Real-time properties
        this.lastUpdateTime = Math.floor(Date.now() / 1000);
        this.isOnline = true;
        this.updateInterval = null;
        
        // arxcikopoiisi
        this.initProperties();
        this.initMediaUpload();
        this.initDraggableMedia();
        this.initEditDeleteHandlers();
        this.initMediaPreviews();
        this.initQuillEditors();

        //createfunctions
        this.loadCanvasMediaOnInit();
        this.initRealTimeUpdates(); 
    }

    // Αρχικοποίηση properties πρώτα
    initProperties() {
        this.currentCanvasId = this.getCurrentCanvasId();
        this.currentUserId = this.getCurrentUserId();
        console.log('MediaManager initialized:', {
            canvasId: this.currentCanvasId,
            userId: this.currentUserId
        });
    }

    // Αρχικοποίηση Quill editors
    initQuillEditors() {
        try {
            const editorElement = document.getElementById('editNoteEditor');
            if (editorElement && !this.richTextQuill) {
                this.richTextQuill = new Quill('#editNoteEditor', {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'header': [1, 2, 3, false] }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'color': [] }, { 'background': [] }],
                            ['link'],
                            ['clean']
                        ]
                    },
                    placeholder: 'Γράψτε το περιεχόμενο της σημείωσής σας...'
                });
                console.log('Quill editor initialized successfully');
            }
        } catch (error) {
            console.error('Error initializing Quill editor:', error);
        }
    }

    // Βοηθητικές μέθοδοι για IDs
    getCurrentCanvasId() {
        const metaTag = document.querySelector('meta[name="current-canva-id"]');
        if (metaTag && metaTag.content) {
            return metaTag.content;
        }
        
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('id')) {
            return urlParams.get('id');
        }
        if (urlParams.has('canva_id')) {
            return urlParams.get('canva_id');
        }
        
        console.warn('Canvas ID not found');
        return null;
    }

    getCurrentUserId() {
        const metaTag = document.querySelector('meta[name="current-user-id"]');
        if (metaTag && metaTag.content) {
            return parseInt(metaTag.content);
        }
        
        const sessionUserId = sessionStorage.getItem('user_id');
        if (sessionUserId) {
            return parseInt(sessionUserId);
        }
        
        console.warn('User ID not found');
        return null;
    }

   async fetchMediaUpdates() {
    if (!this.currentCanvasId) return;

    try {
        const currentUserId = this.getCurrentUserId();
        if (!currentUserId) return;

        //  ADMIN MODE ΑΠΟ ΤΟ URL ΤΟΥ BROWSER
        const urlParams = new URLSearchParams(window.location.search);
        const isAdminParam = urlParams.get('admin') === '1' ? '&admin=1' : '';

        //  isAdminParam ΣΤΟ URL ΤΟΥ FETCH
        const url = `fetch_media_updates.php?canva_id=${this.currentCanvasId}&last_update=${this.lastUpdateTime}&user_id=${currentUserId}${isAdminParam}`;
        
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error ${response.status}`);
        
        const data = await response.json();
        if (!data.success) throw new Error(data.error);
        
        this.lastUpdateTime = data.timestamp || Math.floor(Date.now() / 1000);

        //delte sync
       
        // Αν η PHP μας στέλνει τα active_ids, σβήνουμε ό,τι λείπει από την οθόνη
        if (data.active_ids && Array.isArray(data.active_ids)) {
            document.querySelectorAll('.media-item').forEach(el => {
                const idOnScreen = parseInt(el.dataset.id);
                if (!data.active_ids.includes(idOnScreen)) {
                    console.log(`Το στοιχείο ${idOnScreen} διαγράφηκε. Αφαίρεση...`);
                    el.remove();
                }
            });
        }

        // enimerosi neon stoixeion
        if (data.media && data.media.length > 0) {
            this.handleMediaUpdates(data.media);
        }
        
        if (data.cursors && data.cursors.length > 0) {
            this.handleCursorUpdates(data.cursors);
        }
        
    } catch (error) {
        console.error('Error fetching media updates:', error);
    }
}
    // REAL-TIME UPDATES
    initRealTimeUpdates() {
        if (!this.currentCanvasId || !this.currentUserId) {
            console.warn('Cannot initialize real-time updates: missing IDs');
            setTimeout(() => this.initRealTimeUpdates(), 1000);
            return;
        }

        this.updateInterval = setInterval(() => {
            if (this.isOnline && this.currentCanvasId) {
                this.fetchMediaUpdates();
            }
        }, 3000);

        window.addEventListener('online', () => {
            this.isOnline = true;
            this.showNotification('Συνδεθήκατε ξανά online', 'success');
            this.fetchMediaUpdates();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.showNotification('Είστε offline - οι αλλαγές μπορεί να μην συγχρονιστούν', 'warning');
        });
    }
   

handleCursorUpdates(cursors) {
    //delete palion cursors
   
    document.querySelectorAll('.remote-cursor').forEach(el => {
        if (!el.dataset.lastUpdate || Date.now() - parseInt(el.dataset.lastUpdate) > 2000) {
            el.remove();
        }
    });
    
    // enimerosi dimoyrsia neon cursors
    cursors.forEach(cursor => {
        if (cursor.user_id !== this.currentUserId) {
            let cursorEl = document.querySelector(`.remote-cursor[data-user-id="${cursor.user_id}"]`);
            
            if (!cursorEl) {
                cursorEl = document.createElement('div');
                cursorEl.className = 'remote-cursor';
                cursorEl.dataset.userId = cursor.user_id;
                cursorEl.innerHTML = `
                    <div class="cursor-arrow">👆</div>
                    <div class="cursor-name">${cursor.username || 'Χρήστης'}</div>
                `;
                const canvas = this.getCanvasContainer();
                if (canvas) {
                    canvas.appendChild(cursorEl);
                }
            }
            
            // eniemrosi thesis
            cursorEl.style.left = `${cursor.x}px`;
            cursorEl.style.top = `${cursor.y}px`;
            cursorEl.dataset.lastUpdate = Date.now();
        }
    });
}

//functions emfanisis media meta to add ton media

createMediaElement(media) {
    const div = document.createElement('div');
    div.className = 'draggable media-item';
    div.dataset.id = media.id;
    div.dataset.type = media.type;
    div.style.cssText = `position: absolute; left: ${media.position_x}px; top: ${media.position_y}px; width: 250px; border: 1px solid #ddd; border-radius: 8px; background: white; padding: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: move; z-index: 100;`;

    const displayName = media.original_filename || "Αρχείο";
    let src = media.data;
    const isYouTube = src && (src.includes('youtube.com') || src.includes('youtu.be'));
    const type = media.type ? media.type.toLowerCase() : '';

    if (src && !isYouTube && !src.startsWith('http') && !src.startsWith('/noteapp')) {
        src = '/noteapp/api/canva/' + src;
    }

    let contentHtml = '';
    //eplogi perixeomeno basiei typis
   
    if (type.includes('image')) {
        contentHtml = `<img src="${src}" class="img-fluid rounded border sync-media-img" />`;
    } else if (isYouTube) {
        let vId = src.includes('v=') ? src.split('v=')[1].split('&')[0] : src.split('/').pop();
        contentHtml = `<div class="ratio ratio-16x9 mb-2"><iframe src="https://www.youtube.com/embed/${vId}" class="sync-media-youtube" frameborder="0" allowfullscreen></iframe></div>`;
    } else if (type.includes('video')) {
        contentHtml = `<video controls class="w-100 rounded border sync-media-video"><source src="${src}" type="${media.type}"></video>`;
    } else if (type === 'text' || type === 'note') {
        contentHtml = `<div class="note-box p-2 bg-warning bg-opacity-10 border rounded"><p class="small mb-0 sync-media-content" style="white-space: pre-wrap;">${media.content || ''}</p></div>`;
    } else {
        let icon = 'bi-file-earmark';
        if (displayName.endsWith('.pdf')) icon = 'bi-file-earmark-pdf text-danger';
        else if (displayName.endsWith('.doc') || displayName.endsWith('.docx')) icon = 'bi-file-earmark-word text-primary';
        contentHtml = `<div class="file-box p-3 bg-light border rounded text-center"><i class="bi ${icon}" style="font-size: 2.5rem;"></i></div>`;
    }

    div.innerHTML = `
        <div class="media-actions mb-2 d-flex justify-content-between">
            <button class="btn btn-xs btn-outline-primary edit-media" data-id="${media.id}"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-xs btn-outline-danger delete-media" data-id="${media.id}"><i class="bi bi-trash"></i></button>
        </div>
        <div class="media-body-sync">
            ${contentHtml}
            <p class="small mt-2 mb-1 fw-bold sync-media-title text-truncate">${displayName}</p>
            <div class="sync-media-comments mt-2 p-1 border-top" style="font-size: 0.75rem; color: #555; font-style: italic;">
                ${media.description ? `<i class="bi bi-chat-left-text"></i> <span>${media.description}</span>` : ''}
            </div>
        </div>
        ${!isYouTube ? `<a href="/noteapp/api/canva/download.php?id=${media.id}" class="btn btn-xs btn-outline-dark w-100 mt-2">Λήψη</a>` : ''}
    `;
    return div;
}

//enimerosi to lockarismos toy media
    updateMediaLockStatus(mediaElement, mediaData) {
        if (mediaData.locked_by) {
            mediaElement.dataset.lockedBy = mediaData.locked_by;
            let lockEl = mediaElement.querySelector('.lock-indicator');
            if (!lockEl) {
                lockEl = document.createElement('div');
                lockEl.className = 'lock-indicator';
                lockEl.style.cssText = 'position: absolute; top: -12px; right: 5px; background: #ffc107; padding: 2px 5px; border-radius: 2px; font-size: 15px;';
                mediaElement.prepend(lockEl);
            }
            lockEl.innerHTML = `🔒 ${mediaData.locked_by_name || 'Κλειδωμένο'}`;
        } else {
            mediaElement.dataset.lockedBy = '';
            const lockEl = mediaElement.querySelector('.lock-indicator');
            if (lockEl) lockEl.remove();
        }
    }
    //fucntis media add

    addMediaToCanvas(mediaData) {
    try {
        // Αν είναι σημείωση, αγνόησε
        if (mediaData.type === 'rich_note' || mediaData.type === 'note' || mediaData.note_id) {
            console.log('Note ignored by MediaManager');
            return;
        }
        
        const mediaElement = this.createMediaElement(mediaData);
        if (mediaElement) {
            const canvas = this.getCanvasContainer();
            canvas.appendChild(mediaElement);
            this.initDraggableForElement(mediaElement);
        }
    } catch (error) {
        console.error('Error adding media to canvas:', error);
    }
}
    // MEDIA LOADING AND DISPLAY
    async loadCanvasMediaOnInit() {
        if (this.currentCanvasId) {
            await this.loadCanvasMedia(this.currentCanvasId);
        } else {
            console.error('Δεν μπορώ να φορτώσω media - λείπει canvas ID');
        }
    }

    async loadCanvasMedia(canvasId) {
    try {
        // an den yparxei to canvasid den yparxei einia null diavase to meta to tag
        
       
        if (!canvasId || canvasId === 'undefined') {
            canvasId = document.querySelector('meta[name="current-canva-id"]')?.content;
        }

        //  UserId από meta tag (πιο σίγουρο από το this.getCurrentUserId)
        const currentUserId = document.querySelector('meta[name="current-user-id"]')?.content;

        //elgoxs an exoyme ta apriatita stoixeia
        if (!canvasId || !currentUserId) {
            console.warn('Αναμονή για IDs... (CanvasID:', canvasId, 'UserID:', currentUserId, ')');
            return; //
        }

        const response = await fetch(`get_all_media.php?canva_id=${canvasId}&user_id=${currentUserId}`);
        
        // Έλεγχος αν η απόκριση είναι valid JSON
        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error("Η PHP επέστρεψε μη έγκυρο JSON:", text);
            throw new Error("Σφάλμα απόκρισης διακομιστή");
        }
        
        if (!response.ok || result.success === false) {
            throw new Error(result.error || 'Αποτυχία φόρτωσης πολυμέσων');
        }
        
        //emfnisi ton dedoemenon
        this.displayMediaOnCanvas(result.media || [], result.notes || []);

    } catch (error) {
        console.error('Σφάλμα φόρτωσης πολυμέσων:', error);
        //oxi error 
       
        if (canvasId !== 'undefined') {
            this.showError('Δεν μπορούν να φορτωθούν τα πολυμέσα: ' + error.message);
        }
    }
}
    
    getCanvasContainer() {
        return document.getElementById('notesBoard') || 
               document.getElementById('canvas-container') ||
               document.querySelector('.main-content') ||
               document.querySelector('main');
    }

    createCanvasContainer() {
        const container = document.createElement('div');
        container.id = 'canvas-container';
        container.className = 'canvas-container';
        container.style.cssText = `
            width: 100%;
            height: 100vh;
            position: relative;
            overflow: hidden;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            margin: 20px 0;
        `;
        
        const message = document.createElement('div');
        message.style.cssText = `
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: #6c757d;
        `;
        message.innerHTML = 'Canvas Container - Εδώ θα εμφανίζονται τα πολυμέσα';
        
        container.appendChild(message);
        
        const mainContent = document.querySelector('main') || 
                           document.querySelector('.content') || 
                           document.querySelector('.container') ||
                           document.querySelector('body');
        
        if (mainContent) {
            mainContent.appendChild(container);
            this.displayMediaOnCanvas([], []);
        }
    }

    displayMediaOnCanvas(media, notes) {
    const canvas = this.getCanvasContainer();
    if (!canvas) return;

    
 

    if (Array.isArray(media)) {
        media.forEach(item => {
            //elegxoa n yparxei idi ato arxeuo stn jamva 
          
            let existingElement = canvas.querySelector(`.media-item[data-id="${item.id}"]`);
            
            if (!existingElement) {
               
               // an den yparxei tite mono to dumpyrgoyme kai to prosthesoyme 
              
                const mediaElement = this.createMediaElement(item);
                if (mediaElement) {
                    canvas.appendChild(mediaElement);
                }
            } else {
                //anyoarxei idi enimeromenoyme ti thesi toy an den to koynaei kapoiso
              
                if (!existingElement.classList.contains('is-dragging')) {
                    existingElement.style.left = item.position_x + 'px';
                    existingElement.style.top = item.position_y + 'px';
                }
            }
        });
    }

    // energipisi the thesi toy polymeso 
    this.initDraggableMedia();
}
//enimerosi vste n afianotai ta polymesa kai stin alli selida  diladi syxeonimsos
handleMediaUpdates(updatedMedia) {
    updatedMedia.forEach(media => {
        const el = document.querySelector(`.media-item[data-id="${media.id}"]`);
        
        if (el) {
            if (media.deleted_at) { el.remove(); return; }


         
            const imgEl = el.querySelector('img, iframe, video source, video');
            const currentSrc = imgEl ? (imgEl.src || imgEl.currentSrc) : "";
            const currentType = el.dataset.type || "";
            // ----------------------------------------------------------------

          
            if (media.data && (!currentSrc.includes(media.data) || media.type !== currentType)) {
                console.log("Αλλαγή αρχείου/τύπου, αναδημιουργία στοιχείου...");
                const temp = this.createMediaElement(media);
                el.innerHTML = temp.innerHTML;
                el.dataset.type = media.type;
            } else {
           
                const title = el.querySelector('.sync-media-title');
                if (title) title.innerText = media.original_filename;
                
                const content = el.querySelector('.sync-media-content');
                if (content && media.content !== undefined) content.innerHTML = media.content;

              
                const commentText = el.querySelector('.comment-text');
                if (commentText) {
                    const newComment = media.comment || '<span class="text-muted">Χωρίς σχόλια</span>';
                    if (commentText.innerHTML !== newComment) {
                        commentText.innerHTML = newComment;
                    }
                }
            }

            // sync rhesis mono an den to metakinsie o trexon xristis
         
            if (!el.classList.contains('dragging')) {
                el.style.left = `${media.position_x}px`;
                el.style.top = `${media.position_y}px`;
            }

            // sync lock status
            
            
            this.updateMediaLockStatus(el, media);

        } else if (!media.deleted_at) {
            // edn yarxei to stoixei to creatw
           
            this.addMediaToCanvas(media);
        }
    });
}



createMediaElement(media) {
    const div = document.createElement('div');
    // Προσθήκη κλάσης αν είναι κλειδωμένο
    div.className = `draggable media-item ${media.locked_by ? 'locked-item' : ''}`;
    div.dataset.id = media.id;
    div.dataset.type = media.type;

    div.dataset.src = media.data; 
    // ---------------------
    div.style.cssText = `position: absolute; left: ${media.position_x}px; top: ${media.position_y}px; width: 260px; border: 1px solid #ddd; border-radius: 10px; background: white; padding: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); cursor: move; z-index: 100;`;

    const displayName = media.original_filename || "Αρχείο";
   
   
    let src = media.data;
    const isYouTube = src && (src.includes('youtube.com') || src.includes('youtu.be'));
    const type = media.type ? media.type.toLowerCase() : '';
    const isLocalVideo = type.includes('video') || displayName.toLowerCase().endsWith('.mp4');

    if (src && !isYouTube && !src.startsWith('http') && !src.startsWith('/noteapp')) {
        src = '/noteapp/api/canva/' + src;
    }
    

    let contentHtml = '';
    if (isYouTube) {
        const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
        const match = src.match(regExp);
        const vId = (match && match[2].length === 11) ? match[2] : null;
        
        contentHtml = `<div class="ratio ratio-16x9 mb-2">
            <iframe src="https://www.youtube.com/embed/${vId}" class="sync-media-youtube" frameborder="0" allowfullscreen></iframe>
        </div>`;
    }

    // ΚΥΡΙΩΣ ΠΕΡΙΕΧΟΜΕΝΟ ΑΝΑ ΤΥΠΟ
    if (type.includes('image')) {
        contentHtml = `<img src="${src}" class="img-fluid rounded border sync-media-img" />`;
    } 
    else if (isYouTube) {
        let vId = src.includes('v=') ? src.split('v=')[1].split('&')[0] : src.split('/').pop();
        contentHtml = `
            <div class="ratio ratio-16x9 mb-2">
                <iframe src="https://www.youtube.com/embed/${vId}" class="sync-media-youtube" frameborder="0" allowfullscreen></iframe>
            </div>
            <a href="${src}" target="_blank" class="btn btn-sm btn-danger w-100 mb-2">
                <i class="bi bi-youtube"></i> Προβολή στο YouTube
            </a>`;
    } 
    else if (isLocalVideo) {
        contentHtml = `<video controls class="w-100 rounded border sync-media-video"><source src="${src}" type="video/mp4"></video>`;
    } 
    else if (type === 'text' || type === 'note' || type === 'rich_note') {
        contentHtml = `<div class="note-box p-2 bg-warning bg-opacity-10 border rounded border-warning">
                        <p class="small mb-0 sync-media-content" style="white-space: pre-wrap; min-height: 50px;">${media.content || ''}</p>
                       </div>`;
    } 
    else {
        let icon = displayName.endsWith('.pdf') ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-word text-primary';
        contentHtml = `<div class="file-box p-3 bg-light border rounded text-center"><i class="bi ${icon}" style="font-size: 2.5rem;"></i></div>`;
    }

    // LOCK INDICATOR (Εμφανίζεται μόνο αν είναι κλειδωμένο)
    const lockHtml = media.locked_by ? 
        `<div class="lock-status-badge badge bg-danger w-100 mb-2">
            <i class="bi bi-person-fill-lock"></i> ${media.locked_by_name || 'Κλειδωμένο'}
         </div>` : '';

    div.innerHTML = `
        <div class="media-actions mb-2 d-flex justify-content-between">
            <button class="btn btn-xs btn-outline-primary edit-media" data-id="${media.id}"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-xs btn-outline-danger delete-media" data-id="${media.id}"><i class="bi bi-trash"></i></button>
        </div>
        ${lockHtml}
        <div class="media-body-sync">
            ${contentHtml}
            <p class="small mt-2 mb-1 fw-bold sync-media-title text-truncate">${displayName}</p>
            
            // Μέσα στην createMediaElement
<div class="sync-media-comments mt-2 p-2 border-top bg-light rounded" style="font-size: 0.8rem;">
    <i class="bi bi-chat-dots"></i> 
    <span class="comment-text">${media.comment || 'Χωρίς σχόλια'}</span>
</div>
        ${!isYouTube ? `<a href="/noteapp/api/canva/download.php?id=${media.id}" class="btn btn-xs btn-outline-dark w-100 mt-2">Λήψη αρχείου</a>` : ''}
    `;
    return div;
}


    // DRAG & DROP
    initDraggableMedia() {
        if (typeof interact === 'undefined') return;

        interact('.draggable').draggable({
            inertia: true,
            modifiers: [
                interact.modifiers.restrictRect({
                    restriction: 'parent',
                    endOnly: true
                })
            ],
            autoScroll: true,
            listeners: {
                start: (event) => {
                    event.target.classList.add('dragging');
                    event.target.style.zIndex = '10000';
                },
                move: (event) => {
                    const target = event.target;
                    const x = (parseFloat(target.style.left) || 0) + event.dx;
                    const y = (parseFloat(target.style.top) || 0) + event.dy;

                    target.style.left = `${x}px`;
                    target.style.top = `${y}px`;
                },
                end: (event) => {
                    event.target.classList.remove('dragging');
                    event.target.style.zIndex = '';
                    this.saveMediaPosition(event.target);
                }
            }
        });
    }

    initDraggableForElement(element) {
        if (typeof interact === 'undefined') return;

        interact(element).draggable({
            inertia: true,
            modifiers: [
                interact.modifiers.restrictRect({
                    restriction: 'parent',
                    endOnly: true
                })
            ],
            autoScroll: true,
            listeners: {
                start: (event) => {
                    event.target.classList.add('dragging');
                    event.target.style.zIndex = '10000';
                },
                move: (event) => {
                    const target = event.target;
                    const x = (parseFloat(target.style.left) || 0) + event.dx;
                    const y = (parseFloat(target.style.top) || 0) + event.dy;

                    target.style.left = `${x}px`;
                    target.style.top = `${y}px`;
                },
                end: (event) => {
                    event.target.classList.remove('dragging');
                    event.target.style.zIndex = '';
                    this.saveMediaPosition(event.target);
                }
            }
        });
    }

    async saveMediaPosition(mediaElement) {
        try {
            const response = await fetch('save_media_position.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    media_id: mediaElement.dataset.id,
                    position_x: parseInt(mediaElement.style.left) || 0,
                    position_y: parseInt(mediaElement.style.top) || 0,
                    canva_id: this.currentCanvasId
                })
            });

            const result = await response.json();
            
            if (!response.ok || !result.success) {
                throw new Error(result.error || 'Αποτυχία ενημέρωσης θέσης');
            }
        } catch (error) {
            console.error('Σφάλμα ενημέρωσης θέσης:', error);
        }
    }

      async saveMediaChanges() {
    const modal = document.getElementById('editMediaModal');
    const mediaId = modal.dataset.mediaId;
    const mediaType = modal.dataset.mediaType;
    
    try {
       
        const editNoteContent = document.getElementById('editNoteContent');
        if ((mediaType === 'text' || mediaType === 'rich_note') && !editNoteContent) {
            throw new Error('Το πεδίο περιεχομένου δεν βρέθηκε');
        }

        const formData = new FormData();
        formData.append('id', mediaId);
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            throw new Error('CSRF token not found');
        }

        // Loading state
        const saveBtn = modal.querySelector('.btn-primary');
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Αποθήκευση...';
        saveBtn.disabled = true;

        if (mediaType === 'image') {
            formData.append('comment', document.getElementById('editImageComment').value);
            
            const fileInput = document.getElementById('editImageUpload');
            if (fileInput.files[0]) {
                formData.append('file', fileInput.files[0]);
            }
            
        } else if (mediaType === 'text' || mediaType === 'rich_note') {
            const content = editNoteContent.value;
            if (!content.trim()) {
                throw new Error('Το περιεχόμενο δεν μπορεί να είναι κενό');
            }
            formData.append('content', content);
            formData.append('comment', document.getElementById('editNoteComment').value);
            
        } else if (mediaType === 'file') {
            formData.append('comment', document.getElementById('editFileComment').value);
            
            const fileInput = document.getElementById('editFileUpload');
            if (fileInput.files[0]) {
                formData.append('file', fileInput.files[0]);
            }
            
        } else if (mediaType === 'video') {
    formData.append('comment', document.getElementById('editVideoComment').value);
    
    // YouTube URL
    const youtubeUrl = document.getElementById('editVideoUrl').value.trim();
    if (youtubeUrl) {
        formData.append('url', youtubeUrl);
        
        //  YouTube metadata
        formData.append('type', 'youtube');
        formData.append('content', youtubeUrl);
        
        // create name file youtube
      
        const youtubeFilename = "YouTube Video: " + youtubeUrl;
        formData.append('original_filename', youtubeFilename);
    }
    
    //local video 
   
    const fileInput = document.getElementById('editVideoUpload');
    if (fileInput.files[0]) {
        formData.append('file', fileInput.files[0]);
    }
    
    //an den yoarxei outey yurl yte arxei kratmae ta yparxon DEDOMENA
   
    if (!youtubeUrl && !fileInput.files[0]) {
        formData.append('keep_existing', 'true');
    }
}
        
        const response = await fetch('update_media.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': csrfToken
            }
        });
        
        // Restore button state
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            throw new Error('Ο server επέστρεψε μη έγκυρη απάντηση');
        }
        
        const result = await response.json();
        
        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Αποτυχία ενημέρωσης');
        }
        
        Swal.fire({
            icon: 'success',
            title: 'Επιτυχία!',
            text: 'Τα δεδομένα ενημερώθηκαν.',
            timer: 1500
        }).then(() => {
            // Κλείσιμο modal με καθυστέρηση για better UX
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            } else {
                $(modal).modal('hide');
            }
            
            // Soft refresh αντί για full reload
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });
        
    } catch (error) {
        // Restore button state σε περίπτωση error
        const saveBtn = modal.querySelector('.btn-primary');
        if (saveBtn) {
            saveBtn.innerHTML = 'Αποθήκευση';
            saveBtn.disabled = false;
        }
        
        console.error('Σφάλμα αποθήκευσης:', error);
        Swal.fire({
            icon: 'error',
            title: 'Σφάλμα',
            text: error.message
        });
    }
}
        

    // MEDIA UPLOAD
    initMediaUpload() {
    document.getElementById('insertMediaBtn').addEventListener('click', async () => {
        const activeTab = document.querySelector('.tab-pane.active');
        const form = document.getElementById('mediaForm');
        const formData = new FormData(form);
        
        formData.append('canva_id', this.currentCanvasId);

        // --- ΕΛΕΓΧΟΣ ΑΝΑ TAB ---
        if (activeTab.id === 'tabImage') {
            const file = document.getElementById('imageUpload').files[0];
            if (!file) return Swal.fire({ icon: 'error', text: 'Επιλέξτε εικόνα!' });
            formData.append('type', 'image');
            formData.append('file', file);

        } else if (activeTab.id === 'tabVideo') {
            const videoUrl = document.getElementById('videoUrl').value.trim();
            const videoFile = document.getElementById('videoUpload').files[0];
            formData.append('type', 'video');
            if (videoUrl) {
                formData.append('url', videoUrl);
            } else if (videoFile) {
                formData.append('file', videoFile);
            }

        } else if (activeTab.id === 'tabFile') {
            const file = document.getElementById('fileUpload').files[0];
            if (!file) return Swal.fire({ icon: 'error', text: 'Επιλέξτε αρχείο!' });
            formData.append('type', 'file');
            formData.append('file', file);

        }
        else if (activeTab.id === 'tabNote') {
    const text = document.getElementById('noteText').value.trim();
    if (!text) return Swal.fire({ icon: 'error', text: 'Εισάγετε κείμενο σημείωσης!' });

    // Δημιουργούμε Blob σαν .txt
    const blob = new Blob([text], { type: 'text/plain' });

    // Μετατρέπουμε το Blob σε File
    const file = new File([blob], 'note.txt', { type: 'text/plain' });

    formData.append('type', 'file'); // στέλνουμε σαν κανονικό αρχείο
    formData.append('file', file);


        } else if (activeTab.id === 'tabRichNote') {
            const content = this.richTextQuill.root.innerHTML.trim();
            formData.append('type', 'rich_note');
            formData.append('content', content);
        }

        try {
            Swal.showLoading();
            const response = await fetch('save_media.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (result.status === 'success') {
                Swal.fire({ icon: 'success', title: 'Επιτυχία!', timer: 1500 });
                form.reset();
                if(document.getElementById('simpleNoteText')) document.getElementById('simpleNoteText').value = '';
                
                $('#mediaModal').modal('hide');

                // ΑΜΕΣΗ ΕΜΦΑΝΙΣΗ (επειδή η PHP επιστρέφει το result.media)
                if (result.media) {
                    this.displayMediaOnCanvas([result.media], []); 
                } else {
                    await this.loadCanvasMedia(this.currentCanvasId);
                }
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Σφάλμα', text: error.message });
        }
    });
}

    // EDIT & DELETE HANDLERS
    initEditDeleteHandlers() {
        const self = this;
        
        document.addEventListener('click', async (e) => {
            if (e.target.closest('.edit-media')) {
                const mediaId = e.target.closest('.edit-media').dataset.id;
                await self.editMedia(mediaId);
            }
        });

        document.addEventListener('click', async (e) => {
            if (e.target.closest('.delete-media')) {
                const mediaId = e.target.closest('.delete-media').dataset.id;
                await self.deleteMedia(mediaId);
            }
        });
    }

    async editMedia(mediaId,) {
        try {

             

            const response = await fetch(`get_media.php?id=${mediaId}`);
            const media = await response.json();

            if (!response.ok) throw new Error(media.error || 'Αποτυχία φόρτωσης πολυμέσου');

            const modal = document.getElementById('editMediaModal');
            modal.dataset.mediaId = mediaId;
            modal.dataset.mediaType = media.type;

            this.clearEditPreviews();

            switch (media.type) {
                case 'image':
                    document.querySelector('#image-tab').click();
                    const imagePreviewContainer = document.getElementById('editImagePreviewContainer');
                    if (imagePreviewContainer && (media.url || media.data)) {
                        imagePreviewContainer.innerHTML = `
                            <div class="card preview-card">
                                <div class="card-header bg-light">
                                    <small class="fw-bold">Τρέχουσα Εικόνα</small>
                                </div>
                                <div class="card-body text-center">
                                    <img src="${media.url || media.data}" class="img-fluid rounded" style="max-height: 200px;">
                                    <p class="mt-2 small text-muted">${media.original_filename || 'Εικόνα'}</p>
                                </div>
                            </div>
                        `;
                    }
                    document.getElementById('editImageComment').value = media.comment || '';
                    break;
                    
                case 'text':
                case 'rich_note':
                    document.querySelector('#note-tab').click();
                    const notePreviewContainer = document.getElementById('editNotePreviewContainer');
                    if (notePreviewContainer && media.content) {
                        notePreviewContainer.innerHTML = `
                            <div class="card preview-card">
                                <div class="card-header bg-light">
                                    <small class="fw-bold">Τρέχουσα Σημείωση</small>
                                </div>
                                <div class="card-body">
                                    <div class="bg-light p-3 rounded">
                                        <p class="mb-0" style="white-space: pre-wrap;">${media.content}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    document.getElementById('editNoteContent').value = media.content || '';
                    document.getElementById('editNoteComment').value = media.comment || '';
                    break;
                    
                case 'file':
                    document.querySelector('#file-tab').click();
                    const filePreviewContainer = document.getElementById('editFilePreviewContainer');
                    if (filePreviewContainer) {
                        const icon = this.getFileIcon(media.original_filename || media.filename);
                        filePreviewContainer.innerHTML = `
                            <div class="card preview-card">
                                <div class="card-header bg-light">
                                    <small class="fw-bold">Τρέχον Αρχείο</small>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <i class="bi ${icon} fs-1 me-3"></i>
                                        <div>
                                            <h6 class="mb-1">${media.original_filename || media.filename}</h6>
                                            <p class="mb-0 small text-muted">${this.formatFileSize(media.file_size || 0)}</p>
                                            <p class="mb-0 small text-muted">Τύπος: ${media.type || 'Άγνωστος'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    document.getElementById('editFileComment').value = media.comment || '';
                    break;
                    
                case 'video':
                    document.querySelector('#video-tab').click();
                    const videoPreviewContainer = document.getElementById('editVideoPreviewContainer');
                    
                    if (media.url && (media.url.includes('youtube') || media.url.includes('youtu.be') || media.url.includes('vimeo'))) {
                        let embedUrl = '';
                        if (media.url.includes('youtube.com') || media.url.includes('youtu.be')) {
                            const videoId = this.extractYouTubeId(media.url);
                            embedUrl = videoId ? `https://www.youtube.com/embed/${videoId}` : '';
                        } else if (media.url.includes('vimeo.com')) {
                            const videoId = media.url.split('/').pop();
                            embedUrl = `https://player.vimeo.com/video/${videoId}`;
                        }
                        if (embedUrl) {
                            videoPreviewContainer.innerHTML = `
                                <div class="card preview-card">
                                    <div class="card-header bg-light">
                                        <small class="fw-bold">Τρέχον Βίντεο</small>
                                    </div>
                                    <div class="card-body">
                                        <div class="ratio ratio-16x9">
                                            <iframe src="${embedUrl}" frameborder="0" allowfullscreen></iframe>
                                        </div>
                                        <p class="mt-2 small text-muted">Ενσωματωμένο βίντεο</p>
                                    </div>
                                </div>
                            `;
                        }
                        document.getElementById('editVideoUrl').value = media.url || '';
                    } else if (media.data && (media.data.includes('youtube') || media.data.includes('youtu.be') || media.data.includes('vimeo'))) {
                        let embedUrl = '';
                        if (media.data.includes('youtube.com') || media.data.includes('youtu.be')) {
                            const videoId = this.extractYouTubeId(media.data);
                            embedUrl = videoId ? `https://www.youtube.com/embed/${videoId}` : '';
                        } else if (media.data.includes('vimeo.com')) {
                            const videoId = media.data.split('/').pop();
                            embedUrl = `https://player.vimeo.com/video/${videoId}`;
                        }
                        if (embedUrl) {
                            videoPreviewContainer.innerHTML = `
                                <div class="card preview-card">
                                    <div class="card-header bg-light">
                                        <small class="fw-bold">Τρέχον Βίντεο</small>
                                    </div>
                                    <div class="card-body">
                                        <div class="ratio ratio-16x9">
                                            <iframe src="${embedUrl}" frameborder="0" allowfullscreen></iframe>
                                        </div>
                                        <p class="mt-2 small text-muted">Ενσωματωμένο βίντεο</p>
                                    </div>
                                </div>
                            `;
                        }
                        document.getElementById('editVideoUrl').value = media.data || '';
                    } else if (media.url || media.data) {
                        videoPreviewContainer.innerHTML = `
                            <div class="card preview-card">
                                <div class="card-header bg-light">
                                    <small class="fw-bold">Τρέχον Βίντεο</small>
                                </div>
                                <div class="card-body">
                                    <video controls class="w-100 rounded" style="max-height: 200px;">
                                        <source src="${media.url || media.data}" type="video/mp4">
                                        Το πρόγραμμα περιήγησής σας δεν υποστηρίζει βίντεο.
                                    </video>
                                    <p class="mt-2 small text-muted">${media.original_filename || 'Βίντεο'}</p>
                                </div>
                            </div>
                        `;
                        document.getElementById('editVideoUrl').value = '';
                    } else {
                        videoPreviewContainer.innerHTML = '<small class="text-muted">Δεν υπάρχει βίντεο</small>';
                        document.getElementById('editVideoUrl').value = '';
                    }
                    document.getElementById('editVideoComment').value = media.comment || '';
                    break;
            }

            this.initEditMediaPreviews();
            $('#editMediaModal').modal('show');
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Σφάλμα', text: error.message });
            console.error('Σφάλμα φόρτωσης πολυμέσου:', error);
        }
    }

   async deleteMedia(mediaId) {
    
    //  ΕΛΕΓΧΟΣ URL: Κοιτάμε αν το τρέχον URL έχει ?admin=1 ή &admin=1
    const urlParams = new URLSearchParams(window.location.search);
    const isAdminViaUrl = urlParams.get('admin') === '1';
    
    //  ROLE: meta tag 
    const userRole = document.querySelector('meta[name="user-role"]')?.content;
    
    // Αν ισχύει οποιοδήποτε από τα δύο, στέλνουμε το admin=1 στην PHP
    const isAdminParam = (isAdminViaUrl || userRole === 'admin') ? '&admin=1' : '';

    console.log("Αποστολή αιτήματος διαγραφής με παραμέτρους:", isAdminParam);

    try {
        // Τώρα το fetch θα στείλει π.χ. delete_media.php?id=122&admin=1
        const response = await fetch(`delete_media.php?id=${mediaId}${isAdminParam}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
            }
        });

        const result = await response.json();
        
        if (result.success) {
            Swal.fire('Διαγράφηκε!', 'το πολυμεσο διαγραφτηκε με επιτυχια.', 'success');
            // Αφαίρεση από το UI
            document.querySelector(`.media-item[data-id="${mediaId}"]`)?.remove();
        } else {
            // Αν δεις πάλι "Κλειδωμένο", σημαίνει ότι η PHP δεν έλαβε το admin=1
            throw new Error(result.error);
        }
    } catch (error) {
        Swal.fire('Σφάλμα', error.message, 'error');
    }
}
            
    // MEDIA PREVIEWS
    initMediaPreviews() {
        document.getElementById('imageUpload').addEventListener('change', (e) => {
            this.previewImage(e.target.files[0]);
        });

        document.getElementById('videoUrl').addEventListener('input', (e) => {
            this.previewVideoUrl(e.target.value);
        });

        document.getElementById('videoUpload').addEventListener('change', (e) => {
            this.previewVideoFile(e.target.files[0]);
        });

        document.getElementById('fileUpload').addEventListener('change', (e) => {
            this.previewFile(e.target.files[0]);
        });

        document.getElementById('noteText').addEventListener('input', (e) => {
            this.previewNote(e.target.value);
        });

        if (this.richTextQuill) {
            this.richTextQuill.on('text-change', () => {
                this.previewRichNote();
            });
        }
    }

    previewImage(file) {
        const preview = document.getElementById('imagePreview');
        preview.innerHTML = '';

        if (file && file.type.match('image.*')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                preview.innerHTML = `
                    <div class="card preview-card">
                        <div class="card-header bg-light">
                            <small class="fw-bold">Προεπισκόπηση Εικόνας</small>
                        </div>
                        <div class="card-body text-center">
                            <img src="${e.target.result}" class="img-fluid rounded media-preview" style="max-height: 200px;">
                            <p class="mt-2 small text-muted">${file.name} (${this.formatFileSize(file.size)})</p>
                        </div>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
        }
    }

    previewVideoUrl(url) {
        const preview = document.getElementById('videoPreview');
        preview.innerHTML = '';

        if (url) {
            let embedUrl = '';
            
            if (url.includes('youtube.com') || url.includes('youtu.be')) {
                const videoId = this.extractYouTubeId(url);
                embedUrl = videoId ? `https://www.youtube.com/embed/${videoId}` : '';
            } else if (url.includes('vimeo.com')) {
                const videoId = url.split('/').pop();
                embedUrl = `https://player.vimeo.com/video/${videoId}`;
            }

            if (embedUrl) {
                preview.innerHTML = `
                    <div class="card preview-card">
                        <div class="card-header bg-light">
                            <small class="fw-bold">Προεπισκόπηση Βίντεο</small>
                        </div>
                        <div class="card-body">
                            <div class="ratio ratio-16x9">
                                <iframe src="${embedUrl}" frameborder="0" allowfullscreen></iframe>
                            </div>
                            <p class="mt-2 small text-muted">Ενσωματωμένο βίντεο</p>
                        </div>
                    </div>
                `;
            } else if (url) {
                preview.innerHTML = `
                    <div class="alert alert-warning">
                        <small>Δεν ήταν δυνατή η προεπισκόπηση για το URL: ${url}</small>
                    </div>
                `;
            }
        }
    }

    previewVideoFile(file) {
        const preview = document.getElementById('videoPreview');
        preview.innerHTML = '';

        if (file && file.type.match('video.*')) {
            const url = URL.createObjectURL(file);
            preview.innerHTML = `
                <div class="card preview-card">
                    <div class="card-header bg-light">
                        <small class="fw-bold">Προεπισκόπηση Βίντεο</small>
                    </div>
                    <div class="card-body">
                        <video controls class="w-100 rounded media-preview" style="max-height: 200px;">
                            <source src="${url}" type="${file.type}">
                            Το πρόγραμμα περιήγησής σας δεν υποστηρίζει βίντεο.
                        </video>
                        <p class="mt-2 small text-muted">${file.name} (${this.formatFileSize(file.size)})</p>
                    </div>
                </div>
            `;
        }
    }

    previewFile(file) {
        const preview = document.getElementById('filePreview');
        preview.innerHTML = '';

        if (file) {
            const icon = this.getFileIcon(file.name);
            preview.innerHTML = `
                <div class="card preview-card">
                    <div class="card-header bg-light">
                        <small class="fw-bold">Προεπισκόπηση Αρχείου</small>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi ${icon} fs-1 me-3 file-icon-preview"></i>
                            <div>
                                <h6 class="mb-1">${file.name}</h6>
                                <p class="mb-0 small text-muted">${this.formatFileSize(file.size)}</p>
                                <p class="mb-0 small text-muted">Τύπος: ${file.type || 'Άγνωστος'}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    previewNote(text) {
        const preview = document.getElementById('notePreview');
        preview.innerHTML = '';

        if (text.trim()) {
            preview.innerHTML = `
                <div class="card preview-card">
                    <div class="card-header bg-light">
                        <small class="fw-bold">Προεπισκόπηση Σημείωσης</small>
                    </div>
                    <div class="card-body">
                        <div class="bg-light p-3 rounded">
                            <p class="mb-0" style="white-space: pre-wrap;">${text}</p>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    previewRichNote() {
        // Built-in preview through Quill editor
    }

    // EDIT MEDIA PREVIEWS
    initEditMediaPreviews() {
        const editImageUpload = document.getElementById('editImageUpload');
        if (editImageUpload) {
            editImageUpload.addEventListener('change', (e) => {
                this.previewEditImage(e.target.files[0]);
            });
        }

        const editVideoUpload = document.getElementById('editVideoUpload');
        if (editVideoUpload) {
            editVideoUpload.addEventListener('change', (e) => {
                this.previewEditVideoFile(e.target.files[0]);
            });
        }
        
        const editFileUpload = document.getElementById('editFileUpload');
        if (editFileUpload) {
            editFileUpload.addEventListener('change', (e) => {
                this.previewEditFile(e.target.files[0]);
            });
        }

        const editNoteContent = document.getElementById('editNoteContent');
        if (editNoteContent) {
            editNoteContent.addEventListener('input', (e) => {
                this.previewEditNote(e.target.value);
            });
        }

        const videoUrl = document.getElementById('editVideoUrl');
        if (videoUrl) {
            videoUrl.addEventListener('input', (e) => {
                this.previewEditVideoUrl(e.target.value);
            });
        }
    }

    previewEditImage(file) {
        const preview = document.getElementById('editImagePreviewContainer');
        if (!file || !preview) return;

        if (file.type.match('image.*')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                preview.innerHTML = `
                    <div class="card preview-card">
                        <div class="card-header bg-light">
                            <small class="fw-bold">Νέα Προεπισκόπηση Εικόνας</small>
                        </div>
                        <div class="card-body text-center">
                            <img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px;">
                            <p class="mt-2 small text-muted">${file.name} (${this.formatFileSize(file.size)})</p>
                        </div>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
        }
    }

    previewEditVideoFile(file) {
        const preview = document.getElementById('editVideoPreviewContainer');
        if (!file || !preview) return;

        if (file.type.match('video.*')) {
            const url = URL.createObjectURL(file);
            preview.innerHTML = `
                <div class="card preview-card">
                    <div class="card-header bg-light">
                        <small class="fw-bold">Νέα Προεπισκόπηση Βίντεο</small>
                    </div>
                    <div class="card-body">
                        <video controls class="w-100 rounded" style="max-height: 200px;">
                            <source src="${url}" type="${file.type}">
                            Το πρόγραμμα περιήγησής σας δεν υποστηρίζει βίντεο.
                        </video>
                        <p class="mt-2 small text-muted">${file.name} (${this.formatFileSize(file.size)})</p>
                    </div>
                </div>
            `;
        }
    }

    previewEditFile(file) {
        const preview = document.getElementById('editFilePreviewContainer');
        if (!file || !preview) return;

        const icon = this.getFileIcon(file.name);
        preview.innerHTML = `
            <div class="card preview-card">
                <div class="card-header bg-light">
                    <small class="fw-bold">Νέα Προεπισκόπηση Αρχείου</small>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="bi ${icon} fs-1 me-3"></i>
                        <div>
                            <h6 class="mb-1">${file.name}</h6>
                            <p class="mb-0 small text-muted">${this.formatFileSize(file.size)}</p>
                            <p class="mb-0 small text-muted">Τύπος: ${file.type || 'Άγνωστος'}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    previewEditNote(text) {
        const preview = document.getElementById('editNotePreviewContainer');
        if (!preview) return;
        
        if (!text.trim()) {
            preview.innerHTML = '<small class="text-muted">Η προεπισκόπηση θα εμφανιστεί εδώ...</small>';
            return;
        }

        preview.innerHTML = `
            <div class="card preview-card">
                <div class="card-header bg-light">
                    <small class="fw-bold">Νέα Προεπισκόπηση Σημείωσης</small>
                </div>
                <div class="card-body">
                    <div class="bg-light p-3 rounded">
                        <p class="mb-0" style="white-space: pre-wrap;">${text}</p>
                    </div>
                </div>
            </div>
        `;
    }
    
    previewEditVideoUrl(url) {
        const preview = document.getElementById('editVideoPreviewContainer');
        if (!url.trim() || !preview) {
            preview.innerHTML = '<small class="text-muted">Η προεπισκόπηση θα εμφανιστεί εδώ...</small>';
            return;
        }

        let embedUrl = '';
        
        if (url.includes('youtube.com') || url.includes('youtu.be')) {
            const videoId = this.extractYouTubeId(url);
            embedUrl = videoId ? `https://www.youtube.com/embed/${videoId}` : '';
        } else if (url.includes('vimeo.com')) {
            const videoId = url.split('/').pop();
            embedUrl = `https://player.vimeo.com/video/${videoId}`;
        }

        if (embedUrl) {
            preview.innerHTML = `
                <div class="card preview-card">
                    <div class="card-header bg-light">
                        <small class="fw-bold">Νέα Προεπισκόπηση Βίντεο</small>
                    </div>
                    <div class="card-body">
                        <div class="ratio ratio-16x9">
                            <iframe src="${embedUrl}" frameborder="0" allowfullscreen></iframe>
                        </div>
                        <p class="mt-2 small text-muted">Ενσωματωμένο βίντεο</p>
                    </div>
                </div>
            `;
        } else {
            preview.innerHTML = `
                <div class="alert alert-warning">
                    <small>Δεν ήταν δυνατή η προεπισκόπηση για το URL: ${url}</small>
                </div>
            `;
        }
    }



    // UTILITY METHODS
    extractYouTubeId(url) {
        const regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/;
        const match = url.match(regExp);
        return (match && match[7].length === 11) ? match[7] : false;
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const icons = {
            'pdf': 'bi-file-pdf text-danger',
            'doc': 'bi-file-word text-primary',
            'docx': 'bi-file-word text-primary',
            'xls': 'bi-file-excel text-success',
            'xlsx': 'bi-file-excel text-success',
            'ppt': 'bi-file-ppt text-warning',
            'pptx': 'bi-file-ppt text-warning',
            'zip': 'bi-file-zip text-secondary',
            'rar': 'bi-file-zip text-secondary',
            'txt': 'bi-file-text text-info'
        };
        return icons[ext] || 'bi-file-earmark text-secondary';
    }

    isValidYouTubeUrl(url) {
        const patterns = [
            /^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/,
            /^(https?:\/\/)?(www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/,
            /^(https?:\/\/)?(www\.)?youtube\.com\/v\/([a-zA-Z0-9_-]{11})/
        ];
        
        return patterns.some(pattern => pattern.test(url));
    }

    clearPreviews() {
        const previewIds = ['imagePreview', 'videoPreview', 'filePreview', 'notePreview'];
        previewIds.forEach(id => {
            const element = document.getElementById(id);
            if (element) element.innerHTML = '';
        });

        if (this.richTextQuill) {
            this.richTextQuill.root.innerHTML = '';
        }
    }

    clearEditPreviews() {
        const previewContainers = [
            'editImagePreviewContainer',
            'editFilePreviewContainer',
            'editVideoPreviewContainer',
            'editNotePreviewContainer'
        ];
        
        previewContainers.forEach(id => {
            const element = document.getElementById(id);
            if (element) element.innerHTML ='<small class="text-muted">Η προεπισκόπηση θα εμφανιστεί εδώ...</small>';
        });
    }

    // NOTIFICATION & ERROR HANDLING
    showNotification(message, type = 'info') {
        console.log(`Notification [${type}]:`, message);
        
        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
            
            Toast.fire({
                icon: type,
                title: message
            });
        }
    }

    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Σφάλμα',
            text: message,
            timer: 3000
        });
    }

    // CLEANUP
    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        
        if (this.richTextQuill) {
            this.richTextQuill.off('text-change');
        }
        
        console.log('MediaManager destroyed');
    }
}


// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.noteManager = new NoteManager();
    window.mediaManager = new MediaManager();

    // Cleanup when leaving page
    window.addEventListener('beforeunload', () => {
        if (window.mediaManager) {
            window.mediaManager.destroy();
        }
    });
});