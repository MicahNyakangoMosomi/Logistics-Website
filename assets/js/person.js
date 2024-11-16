//sign up a new User
function saveUserData(event) {
    event.preventDefault();
    
    const fName = document.getElementById('firstName').value;
    const lName = document.getElementById('lastName').value;
    const email = document.getElementById('email').value;
    const userName = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    const person = { fName, lName, userName, email, password };

    let people = JSON.parse(localStorage.getItem('people')) || [];
    people.push(person);
    localStorage.setItem('people', JSON.stringify(people));

    alert("User registered successfully!");
    window.location.href = 'signin.html';
}

function validateUser(event) {
    event.preventDefault();
    
    const userName = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    let people = JSON.parse(localStorage.getItem('people')) || [];
    let user = people.find(person => person.userName === userName && person.password === password);

    if (user) {
        alert("Login successful!");
        // Redirect to another page or perform other actions upon successful login
        window.location.href = 'welcome.html';
    } else {
        alert("Invalid username or password.");
    }
}

