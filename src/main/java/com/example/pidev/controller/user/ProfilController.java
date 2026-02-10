package com.example.pidev.controller.user;

import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.stage.FileChooser;
import javafx.stage.Stage;
import javafx.scene.Scene;
import javafx.scene.Parent;
import javafx.fxml.FXMLLoader;
import java.io.File;
import java.io.IOException;
import java.net.URL;
import java.util.ResourceBundle;
import java.nio.file.Files;
import java.nio.file.StandardCopyOption;

public class ProfilController implements Initializable {

    // Champs du profil
    @FXML private TextField firstNameField;
    @FXML private TextField lastNameField;
    @FXML private TextField emailField;
    @FXML private TextField phoneField;
    @FXML private ComboBox<String> facultyComboBox;
    @FXML private TextField departmentField;
    @FXML private ComboBox<String> roleComboBox;
    @FXML private TextField registrationDateField;
    @FXML private TextArea bioTextArea;

    // Photo de profil
    @FXML private ImageView profileImageView;
    @FXML private Button uploadImageButton;

    // Sécurité
    @FXML private PasswordField passwordField;
    @FXML private PasswordField newPasswordField;
    @FXML private ToggleButton emailNotificationsToggle;

    // Données utilisateur (à remplacer par vos propres données)
    private User currentUser;
    private String originalProfileImagePath;
    private File selectedImageFile;

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        loadUserData();
        setupComboBoxes();
        setupEventHandlers();
        loadProfileImage();
    }

    private void loadUserData() {
        // Simuler le chargement des données utilisateur
        // À remplacer par votre logique de chargement réelle
        currentUser = new User();
        currentUser.setFirstName("Jean");
        currentUser.setLastName("Dupont");
        currentUser.setEmail("jean.dupont@universite.edu");
        currentUser.setPhone("+33 1 23 45 67 89");
        currentUser.setFaculty("Informatique");
        currentUser.setDepartment("Département Informatique");
        currentUser.setRole("Administrateur");
        currentUser.setRegistrationDate("15/03/2024");
        currentUser.setBio("Enseignant-chercheur en informatique spécialisé en systèmes distribués.");
        currentUser.setNotificationsEnabled(true);

        // Mettre à jour les champs avec les données utilisateur
        updateFieldsFromUser();
    }

    private void updateFieldsFromUser() {
        firstNameField.setText(currentUser.getFirstName());
        lastNameField.setText(currentUser.getLastName());
        emailField.setText(currentUser.getEmail());
        phoneField.setText(currentUser.getPhone());
        departmentField.setText(currentUser.getDepartment());
        bioTextArea.setText(currentUser.getBio());
        registrationDateField.setText(currentUser.getRegistrationDate());

        // Sélectionner les valeurs dans les ComboBox
        facultyComboBox.getSelectionModel().select(currentUser.getFaculty());
        roleComboBox.getSelectionModel().select(currentUser.getRole());

        // Configurer le toggle des notifications
        emailNotificationsToggle.setSelected(currentUser.isNotificationsEnabled());
        updateToggleButtonText();
    }

    private void setupComboBoxes() {
        // Initialiser la ComboBox des facultés
        facultyComboBox.getItems().addAll(
                "Informatique",
                "Médecine",
                "Droit",
                "Sciences Économiques",
                "Lettres et Sciences Humaines",
                "Sciences",
                "Pharmacie",
                "Polytechnique"
        );

        // Initialiser la ComboBox des rôles
        roleComboBox.getItems().addAll(
                "Administrateur",
                "Organisateur",
                "Participant",
                "Modérateur",
                "Invité"
        );

        // Ajouter des écouteurs pour les changements
        facultyComboBox.valueProperty().addListener((obs, oldVal, newVal) -> {
            currentUser.setFaculty(newVal);
        });

        roleComboBox.valueProperty().addListener((obs, oldVal, newVal) -> {
            currentUser.setRole(newVal);
        });
    }

    private void setupEventHandlers() {
        // Écouteur pour le toggle des notifications
        emailNotificationsToggle.selectedProperty().addListener((obs, oldVal, newVal) -> {
            currentUser.setNotificationsEnabled(newVal);
            updateToggleButtonText();
        });

        // Écouteurs pour la validation des champs
        setupFieldValidators();
    }

    private void updateToggleButtonText() {
        if (emailNotificationsToggle.isSelected()) {
            emailNotificationsToggle.setText("Activé");
            emailNotificationsToggle.setStyle("-fx-background-color: #10b981; -fx-text-fill: white; -fx-font-weight: bold; -fx-background-radius: 15; -fx-padding: 5 15;");
        } else {
            emailNotificationsToggle.setText("Désactivé");
            emailNotificationsToggle.setStyle("-fx-background-color: #dc2626; -fx-text-fill: white; -fx-font-weight: bold; -fx-background-radius: 15; -fx-padding: 5 15;");
        }
    }

    private void setupFieldValidators() {
        // Validation de l'email
        emailField.focusedProperty().addListener((obs, oldVal, newVal) -> {
            if (!newVal) { // Quand le champ perd le focus
                validateEmail();
            }
        });

        // Validation du téléphone
        phoneField.textProperty().addListener((obs, oldVal, newVal) -> {
            if (!newVal.matches("\\+?[0-9\\s\\-]*")) {
                phoneField.setText(oldVal);
            }
        });
    }

    private boolean validateEmail() {
        String email = emailField.getText();
        if (email != null && !email.isEmpty() && !email.matches("^[A-Za-z0-9+_.-]+@(.+)$")) {
            showAlert("Email invalide", "Veuillez entrer une adresse email valide.");
            emailField.requestFocus();
            return false;
        }
        return true;
    }

    @FXML
    private void uploadProfileImage() {
        FileChooser fileChooser = new FileChooser();
        fileChooser.setTitle("Sélectionner une photo de profil");

        // Filtrer les types de fichiers
        fileChooser.getExtensionFilters().addAll(
                new FileChooser.ExtensionFilter("Images", "*.png", "*.jpg", "*.jpeg", "*.gif"),
                new FileChooser.ExtensionFilter("Tous les fichiers", "*.*")
        );

        selectedImageFile = fileChooser.showOpenDialog(uploadImageButton.getScene().getWindow());

        if (selectedImageFile != null) {
            // Vérifier la taille du fichier (max 5MB)
            long fileSize = selectedImageFile.length();
            if (fileSize > 5 * 1024 * 1024) {
                showAlert("Fichier trop volumineux", "La taille maximale est de 5MB.");
                return;
            }

            // Charger l'image dans l'ImageView
            Image image = new Image(selectedImageFile.toURI().toString());
            profileImageView.setImage(image);

            // Sauvegarder temporairement le chemin de l'image
            originalProfileImagePath = selectedImageFile.getAbsolutePath();

            System.out.println("Image sélectionnée: " + selectedImageFile.getAbsolutePath());
        }
    }

    private void loadProfileImage() {
        try {
            // Charger l'image par défaut ou l'image sauvegardée
            String imagePath = (originalProfileImagePath != null) ?
                    "file:" + originalProfileImagePath :
                    getClass().getResource("/com/example/pidev/fxml/user/images/avatar.png").toString();

            Image image = new Image(imagePath);
            profileImageView.setImage(image);
        } catch (Exception e) {
            System.err.println("Erreur lors du chargement de l'image: " + e.getMessage());
            // Charger une image par défaut en cas d'erreur
            profileImageView.setImage(new Image(getClass().getResourceAsStream("/images/default-avatar.png")));
        }
    }

    @FXML
    private void saveProfile() {
        // Valider les champs obligatoires
        if (!validateRequiredFields()) {
            return;
        }

        if (!validateEmail()) {
            return;
        }

        // Mettre à jour l'objet utilisateur
        updateUserFromFields();

        // Sauvegarder l'image si une nouvelle a été sélectionnée
        if (selectedImageFile != null) {
            saveProfileImage();
        }

        // Ici, vous devriez appeler votre service pour sauvegarder dans la base de données
        try {
            // Exemple: userService.updateUser(currentUser);
            System.out.println("Sauvegarde du profil...");
            System.out.println("Nom: " + currentUser.getFirstName() + " " + currentUser.getLastName());
            System.out.println("Email: " + currentUser.getEmail());
            System.out.println("Faculté: " + currentUser.getFaculty());
            System.out.println("Rôle: " + currentUser.getRole());

            showSuccessAlert("Profil sauvegardé", "Vos modifications ont été enregistrées avec succès.");

        } catch (Exception e) {
            showAlert("Erreur", "Une erreur est survenue lors de la sauvegarde: " + e.getMessage());
        }
    }

    private boolean validateRequiredFields() {
        StringBuilder errors = new StringBuilder();

        if (firstNameField.getText().trim().isEmpty()) {
            errors.append("• Le prénom est obligatoire\n");
        }

        if (lastNameField.getText().trim().isEmpty()) {
            errors.append("• Le nom est obligatoire\n");
        }

        if (emailField.getText().trim().isEmpty()) {
            errors.append("• L'email est obligatoire\n");
        }

        if (facultyComboBox.getValue() == null) {
            errors.append("• La faculté est obligatoire\n");
        }

        if (roleComboBox.getValue() == null) {
            errors.append("• Le rôle est obligatoire\n");
        }

        if (errors.length() > 0) {
            showAlert("Champs obligatoires", "Veuillez remplir les champs suivants:\n\n" + errors.toString());
            return false;
        }

        return true;
    }

    private void updateUserFromFields() {
        currentUser.setFirstName(firstNameField.getText().trim());
        currentUser.setLastName(lastNameField.getText().trim());
        currentUser.setEmail(emailField.getText().trim());
        currentUser.setPhone(phoneField.getText().trim());
        currentUser.setDepartment(departmentField.getText().trim());
        currentUser.setBio(bioTextArea.getText().trim());
        currentUser.setNotificationsEnabled(emailNotificationsToggle.isSelected());
    }

    private void saveProfileImage() {
        try {
            // Créer un dossier pour les images de profil si nécessaire
            File profileDir = new File("user-profiles");
            if (!profileDir.exists()) {
                profileDir.mkdir();
            }

            // Générer un nom de fichier unique
            String fileName = "profile_" + currentUser.getEmail().hashCode() +
                    selectedImageFile.getName().substring(selectedImageFile.getName().lastIndexOf("."));
            File destination = new File(profileDir, fileName);

            // Copier le fichier
            Files.copy(selectedImageFile.toPath(), destination.toPath(), StandardCopyOption.REPLACE_EXISTING);

            // Mettre à jour le chemin de l'image
            currentUser.setProfileImagePath(destination.getAbsolutePath());
            originalProfileImagePath = destination.getAbsolutePath();

            System.out.println("Image sauvegardée: " + destination.getAbsolutePath());

        } catch (IOException e) {
            showAlert("Erreur", "Impossible de sauvegarder l'image: " + e.getMessage());
        }
    }

    @FXML
    private void cancelChanges() {
        // Recharger les données originales
        updateFieldsFromUser();

        // Recharger l'image originale
        loadProfileImage();

        showAlert("Modifications annulées", "Toutes les modifications ont été annulées.");
    }

    @FXML
    private void changePassword() {
        String currentPassword = passwordField.getText();
        String newPassword = newPasswordField.getText();

        // Validation basique
        if (currentPassword.isEmpty()) {
            showAlert("Mot de passe actuel requis", "Veuillez entrer votre mot de passe actuel.");
            passwordField.requestFocus();
            return;
        }

        if (newPassword.isEmpty() || newPassword.length() < 6) {
            showAlert("Nouveau mot de passe invalide", "Le nouveau mot de passe doit contenir au moins 6 caractères.");
            newPasswordField.requestFocus();
            return;
        }

        // Ici, vous devriez vérifier le mot de passe actuel avec votre service
        // Exemple: if (!userService.verifyPassword(currentUser.getId(), currentPassword)) { ... }

        try {
            // Simuler le changement de mot de passe
            System.out.println("Changement de mot de passe...");
            System.out.println("Nouveau mot de passe: " + newPassword);

            // Réinitialiser les champs
            passwordField.clear();
            newPasswordField.clear();

            showSuccessAlert("Mot de passe changé", "Votre mot de passe a été changé avec succès.");

        } catch (Exception e) {
            showAlert("Erreur", "Impossible de changer le mot de passe: " + e.getMessage());
        }
    }

    // Méthodes de navigation (similaires à votre contrôleur original)
    @FXML
    private void goToDashboard() throws IOException {
        loadFXML("/com/example/pidev/view/dashboard.fxml");
    }

    @FXML
    private void goToGestionUser() throws IOException {
        loadFXML("/com/example/pidev/view/user/user_management.fxml");
    }

    @FXML
    private void goToProfil() throws IOException {
        // Déjà sur la page de profil
        // Peut-être recharger la page
        loadFXML("/com/example/pidev/view/user/profile.fxml");
    }

    @FXML
    private void goToLogin() throws IOException {
        loadFXML("/com/example/pidev/view/auth/login.fxml");
    }

    private void loadFXML(String fxmlPath) throws IOException {
        Parent root = FXMLLoader.load(getClass().getResource(fxmlPath));
        Stage stage = (Stage) profileImageView.getScene().getWindow();
        Scene scene = new Scene(root);
        stage.setScene(scene);
        stage.show();
    }

    private void showAlert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.WARNING);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private void showSuccessAlert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    // Classe interne pour représenter l'utilisateur (à remplacer par votre entité)
    public static class User {
        private String firstName;
        private String lastName;
        private String email;
        private String phone;
        private String faculty;
        private String department;
        private String role;
        private String registrationDate;
        private String bio;
        private String profileImagePath;
        private boolean notificationsEnabled;

        // Getters et setters
        public String getFirstName() { return firstName; }
        public void setFirstName(String firstName) { this.firstName = firstName; }

        public String getLastName() { return lastName; }
        public void setLastName(String lastName) { this.lastName = lastName; }

        public String getEmail() { return email; }
        public void setEmail(String email) { this.email = email; }

        public String getPhone() { return phone; }
        public void setPhone(String phone) { this.phone = phone; }

        public String getFaculty() { return faculty; }
        public void setFaculty(String faculty) { this.faculty = faculty; }

        public String getDepartment() { return department; }
        public void setDepartment(String department) { this.department = department; }

        public String getRole() { return role; }
        public void setRole(String role) { this.role = role; }

        public String getRegistrationDate() { return registrationDate; }
        public void setRegistrationDate(String registrationDate) { this.registrationDate = registrationDate; }

        public String getBio() { return bio; }
        public void setBio(String bio) { this.bio = bio; }

        public String getProfileImagePath() { return profileImagePath; }
        public void setProfileImagePath(String profileImagePath) { this.profileImagePath = profileImagePath; }

        public boolean isNotificationsEnabled() { return notificationsEnabled; }
        public void setNotificationsEnabled(boolean notificationsEnabled) { this.notificationsEnabled = notificationsEnabled; }
    }
}
