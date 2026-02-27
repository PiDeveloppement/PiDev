package com.example.pidev.service.user;

import java.util.Properties;
import javax.mail.*;
import javax.mail.internet.*;

public class EmailService {

    private static final String SMTP_HOST = "smtp.mailtrap.io";
    private static final String SMTP_PORT = "587";
    private static final String SMTP_USERNAME = "1d95f13a479fea";
    private static final String SMTP_PASSWORD = "1b2a8421539f82";
    private static final String FROM_EMAIL = "noreply@eventflow.com";

    public static void sendResetPasswordEmail(String toEmail, String userName, String token) {
        Properties props = new Properties();
        props.put("mail.smtp.host", SMTP_HOST);
        props.put("mail.smtp.port", SMTP_PORT);
        props.put("mail.smtp.auth", "true");
        props.put("mail.smtp.starttls.enable", "true");
        props.put("mail.debug", "true");

        Session session = Session.getInstance(props, new Authenticator() {
            @Override
            protected PasswordAuthentication getPasswordAuthentication() {
                return new PasswordAuthentication(SMTP_USERNAME, SMTP_PASSWORD);
            }
        });
        session.setDebug(true);

        try {
            Message message = new MimeMessage(session);
            message.setFrom(new InternetAddress(FROM_EMAIL));
            message.setRecipients(Message.RecipientType.TO, InternetAddress.parse(toEmail));
            message.setSubject("🔐 Réinitialisation de votre mot de passe - EventFlow");

            // ✅ NOUVEAU LIEN VERS LE SERVEUR LOCAL (PORT 8085)
            String resetLink = "http://localhost:8085/reset?token=" + token;

            String htmlContent = "<!DOCTYPE html>" +
                    "<html><head><meta charset='UTF-8'>" +
                    "<style>" +
                    "body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }" +
                    ".container { max-width: 500px; margin: auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }" +
                    "h2 { color: #333; }" +
                    ".btn { display: inline-block; padding: 12px 24px; background-color: #4CAF50; color: white; " +
                    "text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }" +
                    ".btn:hover { background-color: #45a049; }" +
                    ".note { color: #666; font-size: 12px; margin-top: 20px; }" +
                    "</style>" +
                    "</head>" +
                    "<body>" +
                    "<div class='container'>" +
                    "<h2>🔐 Réinitialisation de mot de passe</h2>" +
                    "<p>Bonjour <strong>" + userName + "</strong>,</p>" +
                    "<p>Vous avez demandé la réinitialisation de votre mot de passe.</p>" +
                    "<p>Cliquez sur le bouton ci-dessous :</p>" +
                    "<a href='" + resetLink + "' class='btn'>🔄 RÉINITIALISER MON MOT DE PASSE</a>" +
                    "<p class='note'>Ou copiez ce lien dans votre navigateur :<br>" +
                    "<small>" + resetLink + "</small></p>" +
                    "<p><strong>Ce lien expirera dans 1 heure.</strong></p>" +
                    "<p>Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</p>" +
                    "</div>" +
                    "</body></html>";

            message.setContent(htmlContent, "text/html; charset=utf-8");

            System.out.println("📧 Envoi d'email à: " + toEmail);
            Transport.send(message);

            System.out.println("✅ Email envoyé via Mailtrap!");
            System.out.println("🔗 Lien de réinitialisation: " + resetLink);

        } catch (MessagingException e) {
            e.printStackTrace();
            throw new RuntimeException("Erreur lors de l'envoi de l'email: " + e.getMessage());
        }
    }
}