package com.example.pidev.controller.user;

import com.example.pidev.MainController;
import com.example.pidev.model.role.Role;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.role.RoleService;
import com.example.pidev.service.user.UserService;
import com.example.pidev.utils.UserSession;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.StackPane;
import javafx.scene.shape.Circle;
import javafx.scene.text.Text;
import javafx.stage.FileChooser;

import java.io.File;
import java.net.URL;
import java.sql.SQLException;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ResourceBundle;

public class ProfilController implements Initializable {

    // Champs du profil
    @FXML private TextField firstNameField;
    @FXML private TextField lastNameField;
    @FXML private TextField emailField;
    @FXML private TextField phoneField;
    @FXML private ComboBox<String> facultyComboBox;  // Pour les facultés
    @FXML private ComboBox<String> roleComboBox;      // Pour les rôles (disabled)
    @FXML private TextField registrationDateField;
    @FXML private TextArea bioTextArea;
    @FXML private Label bioCharCountLabel;

    // Photo de profil - NOUVEAUX CHAMPS
    @FXML private StackPane avatarContainer;
    @FXML private ImageView profileImageView;
    @FXML private StackPane initialsContainer;
    @FXML private Text userInitialsText;
    @FXML private Button uploadImageButton;

    // Sécurité
    @FXML private TextField currentPasswordField;
    @FXML private PasswordField newPasswordField;
    @FXML private PasswordField confirmPasswordField;

    // Statistiques
    @FXML private Label eventCountLabel;
    @FXML private Label participationCountLabel;
    @FXML private Label roleCountLabel;
    @FXML private Label verificationStatusLabel;
    @FXML private Label lastLoginLabel;
    @FXML private Label userRoleDisplayLabel;
    @FXML private Label userLevelLabel;

    private UserModel currentUser;
    private UserService userService;
    private RoleService roleService;
    private MainController mainController;

    public void setMainController(MainController mainController) {
        this.mainController = mainController;
    }

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        try {
            System.out.println("✅ ProfilController initialisé");
            userService = new UserService();
            roleService = new RoleService();

            // Récupérer l'utilisateur connecté depuis la session
            currentUser = UserSession.getInstance().getCurrentUser();

            if (currentUser != null) {
                // Afficher les infos dans la console
                System.out.println("📌 Faculté de l'utilisateur: " + currentUser.getFaculte());
                System.out.println("📌 Rôle de l'utilisateur: " +
                        (currentUser.getRole() != null ? currentUser.getRole().getRoleName() : "Non défini"));

                // Charger les données
                loadUserDataFromModel();
                loadFacultiesFromDatabase();  // Charger les facultés depuis user_model
                loadRolesFromDatabase();       // Charger les rôles depuis role
                loadProfileImage();             // Charger l'image avec le style circulaire
                updateStatistics();
                setupBioCounter();
                disableReadOnlyFields();

            } else {
                System.err.println("❌ Aucun utilisateur connecté");
                showAlert("Erreur", "Aucun utilisateur connecté");
            }

        } catch (Exception e) {
            e.printStackTrace();
            showAlert("Erreur", "Erreur lors de l'initialisation: " + e.getMessage());
        }
    }

    /**
     * Charge les facultés depuis la table user_model
     */
    private void loadFacultiesFromDatabase() {
        try {
            // Récupérer toutes les facultés uniques depuis user_model
            ObservableList<String> faculties = userService.getAllFacultes();

            if (faculties.isEmpty()) {
                System.out.println("⚠️ Aucune faculté trouvée dans la base de données");
                // Optionnel: Ajouter une valeur par défaut
                faculties.add("Non définie");
            } else {
                System.out.println("✅ " + faculties.size() + " facultés chargées depuis la base");
            }

            facultyComboBox.setItems(faculties);

            // Sélectionner la faculté de l'utilisateur
            String userFaculty = currentUser.getFaculte();
            if (userFaculty != null && !userFaculty.isEmpty()) {
                if (faculties.contains(userFaculty)) {
                    facultyComboBox.setValue(userFaculty);
                    System.out.println("✅ Faculté sélectionnée: " + userFaculty);
                } else {
                    // Ajouter la faculté si elle n'existe pas dans la liste
                    facultyComboBox.getItems().add(userFaculty);
                    facultyComboBox.setValue(userFaculty);
                    System.out.println("➕ Faculté ajoutée: " + userFaculty);
                }
            }

            // Écouter les changements
            facultyComboBox.valueProperty().addListener((obs, oldVal, newVal) -> {
                if (newVal != null && !newVal.equals(oldVal)) {
                    System.out.println("Faculté changée: " + oldVal + " -> " + newVal);
                    currentUser.setFaculte(newVal);
                }
            });

        } catch (Exception e) {
            System.err.println("❌ Erreur chargement facultés: " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Charge les rôles depuis la table role
     */
    private void loadRolesFromDatabase() {
        try {
            // Récupérer tous les noms de rôles depuis role
            ObservableList<String> roles = roleService.getAllRoleNames();

            if (roles.isEmpty()) {
                System.out.println("⚠️ Aucun rôle trouvé dans la base de données");
                roles.add("Non défini");
            } else {
                System.out.println("✅ " + roles.size() + " rôles chargés depuis la base");
            }

            roleComboBox.setItems(roles);

            // Sélectionner le rôle de l'utilisateur
            if (currentUser.getRole() != null) {
                String userRole = currentUser.getRole().getRoleName();
                if (userRole != null && !userRole.isEmpty()) {
                    if (roles.contains(userRole)) {
                        roleComboBox.setValue(userRole);
                        System.out.println("✅ Rôle sélectionné: " + userRole);
                    }
                }
            }

            // Le champ rôle est désactivé (lecture seule)
            roleComboBox.setDisable(true);

        } catch (Exception e) {
            System.err.println("❌ Erreur chargement rôles: " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Charge les données depuis UserModel
     */
    private void loadUserDataFromModel() {
        if (currentUser != null) {
            firstNameField.setText(currentUser.getFirst_Name());
            lastNameField.setText(currentUser.getLast_Name());
            emailField.setText(currentUser.getEmail());
            phoneField.setText(currentUser.getPhone() != null ? currentUser.getPhone() : "");

            // Date d'inscription
            if (currentUser.getRegistrationDate() != null) {
                DateTimeFormatter formatter = DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm");
                registrationDateField.setText(currentUser.getRegistrationDate().format(formatter));
            } else {
                registrationDateField.setText("Non disponible");
            }

            // Biographie
            bioTextArea.setText(currentUser.getBio() != null ? currentUser.getBio() : "");
            if (bioCharCountLabel != null) {
                bioCharCountLabel.setText((currentUser.getBio() != null ? currentUser.getBio().length() : 0) + "/500");
            }

            // Mot de passe actuel
            if (currentPasswordField != null && currentUser.getPassword() != null) {
                currentPasswordField.setText(currentUser.getPassword());
            }

            // Mettre à jour les labels d'affichage
            if (userRoleDisplayLabel != null && currentUser.getRole() != null) {
                userRoleDisplayLabel.setText(currentUser.getRole().getRoleName());
            }
        }
    }

    /**
     * Désactive les champs en lecture seule
     */
    private void disableReadOnlyFields() {
        if (roleComboBox != null) {
            roleComboBox.setDisable(true);
            roleComboBox.setStyle("-fx-background-color: #f1f5f9; -fx-text-fill: #64748b;");
        }

        if (registrationDateField != null) {
            registrationDateField.setDisable(true);
            registrationDateField.setStyle("-fx-background-color: #f1f5f9; -fx-text-fill: #64748b;");
        }
    }

    private void updateStatistics() {
        if (currentUser != null) {
            eventCountLabel.setText("0");
            participationCountLabel.setText("0");
            roleCountLabel.setText(currentUser.getRole() != null ? "1" : "0");

            if (userLevelLabel != null && currentUser.getRole() != null) {
                userLevelLabel.setText(currentUser.getRole().getRoleName());
            }

            verificationStatusLabel.setText("🟢 Compte vérifié");
            lastLoginLabel.setText("Dernière connexion: " +
                    LocalDateTime.now().format(DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm")));
        }
    }

    private void setupBioCounter() {
        bioTextArea.textProperty().addListener((obs, oldVal, newVal) -> {
            int length = newVal.length();
            bioCharCountLabel.setText(length + "/500");

            if (length > 500) {
                bioTextArea.setText(oldVal);
                bioCharCountLabel.setText("500/500 (maximum atteint)");
            }
        });
    }

    /**
     * Charge l'image de profil avec le style circulaire (initiales par défaut)
     */
    private void loadProfileImage() {
        UserSession session = UserSession.getInstance();

        if (currentUser != null) {
            // Afficher les initiales par défaut (comme dans la top bar)
            if (userInitialsText != null) {
                userInitialsText.setText(session.getInitials());
            }

            // Charger la photo si elle existe
            String photoUrl = currentUser.getProfilePictureUrl();
            if (photoUrl != null && !photoUrl.isEmpty()) {
                try {
                    Image image = new Image(photoUrl, 132, 132, true, true);
                    profileImageView.setImage(image);
                    profileImageView.setVisible(true);

                    // Cacher les initiales
                    if (initialsContainer != null) {
                        initialsContainer.setVisible(false);
                    }

                    // Appliquer le clip circulaire à l'image
                    applyCircularClip(profileImageView, 66);

                    System.out.println("✅ Photo de profil chargée depuis: " + photoUrl);

                } catch (Exception e) {
                    System.err.println("❌ Erreur chargement photo: " + e.getMessage());
                    // En cas d'erreur, afficher les initiales
                    profileImageView.setVisible(false);
                    if (initialsContainer != null) {
                        initialsContainer.setVisible(true);
                    }
                }
            } else {
                // Pas de photo, afficher les initiales
                System.out.println("ℹ️ Aucune photo de profil, affichage des initiales: " + session.getInitials());
                profileImageView.setVisible(false);
                if (initialsContainer != null) {
                    initialsContainer.setVisible(true);
                }
            }
        }
    }

    /**
     * Applique un clip circulaire à l'image
     */
    private void applyCircularClip(ImageView imageView, double radius) {
        if (imageView != null && imageView.getImage() != null) {
            Circle clip = new Circle(radius, radius, radius);
            imageView.setClip(clip);
            imageView.setPreserveRatio(true);
            System.out.println("✅ Clip circulaire appliqué (rayon: " + radius + ")");
        }
    }

    @FXML
    private void uploadProfileImage() {
        FileChooser fileChooser = new FileChooser();
        fileChooser.setTitle("Sélectionner une photo de profil");
        fileChooser.getExtensionFilters().addAll(
                new FileChooser.ExtensionFilter("Images", "*.png", "*.jpg", "*.jpeg", "*.gif")
        );

        File selectedImageFile = fileChooser.showOpenDialog(uploadImageButton.getScene().getWindow());

        if (selectedImageFile != null) {
            // Vérifier la taille (max 5MB)
            if (selectedImageFile.length() > 5 * 1024 * 1024) {
                showAlert("Fichier trop volumineux", "La taille maximale est de 5MB.");
                return;
            }

            try {
                // Charger l'image
                Image image = new Image(selectedImageFile.toURI().toString(), 132, 132, true, true);

                // Afficher l'image et cacher les initiales
                profileImageView.setImage(image);
                profileImageView.setVisible(true);
                if (initialsContainer != null) {
                    initialsContainer.setVisible(false);
                }

                // Appliquer le clip circulaire
                applyCircularClip(profileImageView, 66);

                // Sauvegarder le chemin
                currentUser.setProfilePictureUrl(selectedImageFile.toURI().toString());

                System.out.println("✅ Image chargée: " + selectedImageFile.getName());

            } catch (Exception e) {
                showAlert("Erreur", "Impossible de charger l'image: " + e.getMessage());
                e.printStackTrace();
            }
        }
    }

    @FXML
    private void saveProfile() {
        // Mettre à jour UserModel
        currentUser.setFirst_Name(firstNameField.getText().trim());
        currentUser.setLast_Name(lastNameField.getText().trim());
        currentUser.setEmail(emailField.getText().trim());
        currentUser.setPhone(phoneField.getText().trim());
        currentUser.setFaculte(facultyComboBox.getValue());
        currentUser.setBio(bioTextArea.getText().trim());

        try {
            if (userService.updateUser(currentUser)) {
                // Mettre à jour la session
                UserSession.getInstance().updateUserInfo(currentUser);

                // ✅ RAFRAÎCHIR LE HEADER - Appel au MainController
                if (mainController != null) {
                    mainController.refreshHeaderProfile();
                    System.out.println("🔄 Header rafraîchi après modification du profil");
                } else {
                    System.err.println("⚠️ mainController est null, impossible de rafraîchir le header");
                }

                showSuccessAlert("Succès", "Profil mis à jour avec succès");
            } else {
                showAlert("Erreur", "Échec de la mise à jour");
            }
        } catch (Exception e) {
            e.printStackTrace();
            showAlert("Erreur", "Erreur lors de la sauvegarde: " + e.getMessage());
        }
    }

    @FXML
    private void cancelChanges() {
        loadUserDataFromModel();
        loadProfileImage(); // Recharger l'image (revient aux initiales si pas de photo)
        // Re-sélectionner la faculté
        if (currentUser.getFaculte() != null) {
            facultyComboBox.setValue(currentUser.getFaculte());
        }
        showAlert("Annulé", "Modifications annulées");
    }

    @FXML
    private void changePassword() {
        String current = currentPasswordField.getText();
        String newPass = newPasswordField.getText();
        String confirm = confirmPasswordField.getText();

        if (current.isEmpty() || newPass.isEmpty() || confirm.isEmpty()) {
            showAlert("Champs requis", "Tous les champs sont obligatoires");
            return;
        }

        if (!newPass.equals(confirm)) {
            showAlert("Erreur", "Les nouveaux mots de passe ne correspondent pas");
            return;
        }

        if (newPass.length() < 6) {
            showAlert("Erreur", "Le mot de passe doit contenir au moins 6 caractères");
            return;
        }

        if (!current.equals(currentUser.getPassword())) {
            showAlert("Erreur", "Mot de passe actuel incorrect");
            return;
        }

        try {
            currentUser.setPassword(newPass);
            if (userService.updateUser(currentUser)) {
                showSuccessAlert("Succès", "Mot de passe changé avec succès");
                newPasswordField.clear();
                confirmPasswordField.clear();
                currentPasswordField.setText(newPass);
            }
        } catch (Exception e) {
            showAlert("Erreur", "Erreur lors du changement: " + e.getMessage());
        }
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
}