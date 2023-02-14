const checkAttrBtn = document.querySelector('.main-nav button');
const modelCloseBtn = document.querySelector('.model button');
const model = document.querySelector('.model');
const modelHeader = document.querySelector('.model .header');
const modelBody = document.querySelector('.model .body');


modelCloseBtn.addEventListener('click', function (event) {
    model.classList.add('hide');
});


checkAttrBtn.addEventListener('click', function (event) {

    // add loder
    checkAttrBtn.textContent = 'Please wait...';
    checkAttrBtn.setAttribute('disabled', '');
    checkAttrBtn.style.cursor = 'not-allowed';

    window.fetch('./src/api/check_attributes.php', {
        method: 'GET',
    })
        .then(response => response.json())
        .then(data => {

            // display model
            model.classList.remove('hide');

            // console.log(data);

            if (data.status) {
                modelHeader.innerHTML = '<h4>Services with New/Modified Attributes:</h4>';
                modelBody.innerHTML = (data.data.length) ?
                `<p>${data.data.toString()}</p>`
                :
                'No New Attributes Found';
            }
            else {
                modelHeader.innerHTML = '<h4>Error!</h4>';
                modelBody.innerHTML = data.data;  // error message
            }
        })
        .catch(error => {
            alert(error);
        })
        .finally(() => {
            checkAttrBtn.textContent = 'Check New/Modified Attributes';
            checkAttrBtn.removeAttribute('disabled');
            checkAttrBtn.style.cursor = 'default';
        });
});
