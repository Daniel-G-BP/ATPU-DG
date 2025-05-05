document.addEventListener('DOMContentLoaded', () => {
    const typeSelects = document.querySelectorAll('select.typ-vyuky');
  
    typeSelects.forEach(select => {
      updateRowState(select);
      select.addEventListener('change', () => updateRowState(select));
    });
  
    function updateRowState(select) {
        const row = select.closest('tr');
        const value = select.value;
        const input = row.querySelector('.max-studentu-select');
      
        switch (value) {
          case 'P': row.style.backgroundColor = '#d1e7dd'; break;
          case 'C': row.style.backgroundColor = '#fce7cf'; break;
          case 'S': row.style.backgroundColor = '#cff4fc'; break;
          default: row.style.backgroundColor = '';
        }
      
        if (input) {
          const isCviceni = value === 'C';
          input.disabled = !isCviceni;
          if (!isCviceni) input.selectedIndex = -1;
        }
      }
      
  });
  
