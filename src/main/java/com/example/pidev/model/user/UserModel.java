package com.example.pidev.model.user;

import com.example.pidev.model.role.Role;
import jakarta.persistence.*;

public class UserModel {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private int id_User;
    private  String first_Name;
    private String last_Name;
    private String email;
    private String faculte;
    private String password;
    private String confirmerPassword;
    private String roleName;

// a supprimer on va garder juste le id role dans la table user


    @ManyToOne
    @JoinColumn(name="id_role")
    private Role role;
    private int role_Id;

    public UserModel() {

    }
    public UserModel(int id_User,String firstName,String last_Name ,String email,String faculte ,String password,int role_Id ){
        this.id_User=id_User;
        this.email=email;
        this.faculte=faculte;
        this.password=password;
        this.role_Id=role_Id;
        this.first_Name=firstName;
        this.last_Name=last_Name;
    }
    public UserModel(String firstName,String last_Name ,String email,String faculte ,String password,int role_Id ){

        this.email=email;
        this.faculte=faculte;
        this.password=password;
        this.role_Id=role_Id;
        this.first_Name=firstName;
        this.last_Name=last_Name;
    }

    public String getConfirmerPassword() {
        return confirmerPassword;
    }

    public void setConfirmerPassword(String confirmerPassword) {
        this.confirmerPassword = confirmerPassword;
    }

    @Override
    public boolean equals(Object obj) {
        return super.equals(obj);
    }

    @Override
    public int hashCode() {
        return super.hashCode();
    }

    public String getPassword() {
        return password;
    }

    public void setPassword(String password) {
        this.password = password;
    }

    public int getId_User() {
        return id_User;
    }



    public void setEmail(String email) {
        this.email = email;

    }



    public String getEmail() {
        return email;
    }

    public String getFaculte() {
        return faculte;
    }

    public String getLast_Name() {
        return last_Name;
    }

    public void setId_User(int id_User) {
        this.id_User = id_User;
    }

    @Override
    public String toString() {
        return super.toString();
    }

    public String getFirst_Name() {
        return first_Name;
    }

    public void setFaculte(String faculte) {
        this.faculte = faculte;
    }

    public void setFirst_Name(String first_Name) {
        this.first_Name = first_Name;
    }

    public void setLast_Name(String last_Name) {
        this.last_Name = last_Name;
    }

    public int getRole_Id() {
        return role_Id;
    }

    public void setRole_Id(int role_Id) {
        this.role_Id = role_Id;
    }

    public void setRole(Role role) {
        this.role=role;
    }

    public Role getRole() {
        return role;
    }
    public String getRoleName() { return roleName; }
    public void setRoleName(String roleName) { this.roleName = roleName; }

}
