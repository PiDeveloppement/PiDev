package com.melocode.pigestion.service;

import com.melocode.pigestion.model.Question;
import com.melocode.pigestion.utils.MyConnection;
import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class FeedbackService {
    private Connection conn = MyConnection.getInstance().getCnx();

    // Charge les questions pour le Quiz
    public List<Question> chargerQuestionsAleatoires(int idEvent) throws SQLException {
        List<Question> questions = new ArrayList<>();
        // Note : Vérifie bien si ta table s'appelle 'questions' ou 'question' dans ta BDD
        String req = "SELECT * FROM questions WHERE id_event = ? ORDER BY RAND() LIMIT 10";
        PreparedStatement pst = conn.prepareStatement(req);
        pst.setInt(1, idEvent);
        ResultSet rs = pst.executeQuery();
        while (rs.next()) {
            questions.add(new Question(
                    rs.getInt("id_question"),
                    rs.getInt("id_event"),
                    rs.getString("texte_question"),
                    rs.getString("bonne_reponse"),
                    rs.getInt("points")
            ));
        }
        return questions;
    }

    // Enregistre les réponses initiales
    public void enregistrerFeedbackComplet(int idUser, int idQuest, String rep, String comm, int stars) throws SQLException {
        String req = "INSERT INTO feedbacks (id_user, id_question, reponse_donnee, comments, etoiles) VALUES (?, ?, ?, ?, ?)";
        PreparedStatement pst = conn.prepareStatement(req);
        pst.setInt(1, idUser);
        pst.setInt(2, idQuest);
        pst.setString(3, rep);
        pst.setString(4, comm);
        pst.setInt(5, stars);
        pst.executeUpdate();
    }

    // MÉTHODE CORRIGÉE : Pour correspondre à ResultatController.onModifier()
    public void modifierFeedback(int idUser, String comm, int stars) throws SQLException {
        String req = "UPDATE feedbacks SET comments = ?, etoiles = ? WHERE id_user = ?";
        PreparedStatement pst = conn.prepareStatement(req);
        pst.setString(1, comm);
        pst.setInt(2, stars);
        pst.setInt(3, idUser);
        pst.executeUpdate();
    }

    // MÉTHODE AJOUTÉE : Pour correspondre à ResultatController.onSupprimer()
    public void supprimerFeedback(int idUser) throws SQLException {
        String req = "DELETE FROM feedbacks WHERE id_user = ?";
        PreparedStatement pst = conn.prepareStatement(req);
        pst.setInt(1, idUser);
        pst.executeUpdate();
    }
}