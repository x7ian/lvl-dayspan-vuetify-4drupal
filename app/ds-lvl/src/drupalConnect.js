import axios from "axios";

export class drupalConnect {
  constructor() {
    this.debug = false;
    let domain = 'http://lvl.localhost:9001';
    if ( typeof drupalSettings === 'undefined' || drupalSettings === null) {
        this.debug = true;
    } else {
      domain = drupalSettings.ds.host;
    }
    this.paths = {
      domain: domain,
      loginUrl: '/user/login',
      userRoles: '/api/dayspan-vuetify/user-roles',
      userData: '/api/dayspan-vuetify/user',
      entityPath: '/entity/node',
      restTypePath: '/rest/type/node',
      patientsList: '/api/dayspan-vuetify/users/patient',
      viewResults: '/api/view-data',
      token: '/session/token',
      dbg_viewid: 'session_calendar',
      dbg_displayid: 'page_1',
    };
    this.credentials = {
      user: 'x7ian',
      pass: 'G0dsgam3*',
    };
    this.currentLogin = { current_user: false }
    this.xCSRFToken = false;
    this.dbg_xCSRFToken = '0WN5OOya5ytFjeXKp9pC-TRuN36DAKU6-wpXTLWemCc';
  }

  async init() {
    await this.getToken();
    await this.currentUser()
  }
  async getToken() {
    if (this.debug) {
      return this.dbg_xCSRFToken;
    }
    if (this.xCSRFToken) {
      return this.xCSRFToken;
    }
    let headers = {
      'Content-Type': 'application/json',
    };
    try {
      let res = await axios({
          url: this.paths.domain + this.paths.token,
          method: 'get',
          headers: headers,
      });
      this.xCSRFToken = res.data;
      return this.xCSRFToken;
    } catch (error) {
      console.error(error)
    }
  }

  async getUserData(userid) {
    let headers = {
      'Content-Type': 'application/json',
      'X-CSRF-Token': this.xCSRFToken,
    };
    try {
      let res = await axios({
          url: this.paths.domain + this.paths.userData + '/' + userid,
          method: 'get',
          headers: headers,
      });

      //this.currentLogin.current_user.field_name = res.data.field_name;

      return res.data;
    } catch (error) {
      console.error(error)
    }
  }

  async currentUser() {
    if (this.currentLogin.current_user != false) {
      return this.currentLogin.current_user;
    }
    if (this.debug) {
      let loginData = await this.loginToBackend();
      var userid = loginData.current_user.uid
    } else {
      var userid = drupalSettings.ds.userid;
    }
    let userData = await this.getUserData(userid);
    this.currentLogin.current_user = userData;
    return userData;
  }

  async loginToBackend() {
    let data = {
      name: this.credentials.user,
      pass: this.credentials.pass
    };
    let headers = {
      'Content-Type': 'application/json',
      'X-CSRF-Token': this.xCSRFToken,
    };
    try {
      let res = await axios({
          url: this.paths.domain + this.paths.loginUrl + '?_format=json',
          method: 'post',
          headers: headers,
          data: data,
      });
      this.currentLogin = res.data;
      return res.data;
    } catch (error) {
      console.error(error)
    }
  }

  getViewIds() {
    return drupalSettings.ds.view;
  }

  async getEvents() {
    let headers = {
      'Content-Type': 'application/json',
    }
    if (this.debug) {
      var viewid = this.paths.dbg_viewid;
      var displayid = this.paths.dbg_displayid;
    } else {
      var view = this.getViewIds();
      var viewid = view['viewid'];
      var displayid = view['displayid'];
    }
    let params = {
      url: this.paths.domain + this.paths.viewResults
            + '/' + viewid + '/' + displayid,
      method: 'get',
      headers: headers,
    }
    if (this.debug) {
      params['auth'] = {
        username: this.credentials.user,
        password: this.credentials.pass
      }
    }
    try {
      let results = await axios(params)
        .then((response) => {

          return response.data;
        })
        .catch(err => console.log(err));


    } catch (error) {
      console.error(error)
    }
  }

  async createEventOnBackend(item) {
    let url = this.paths.domain + this.paths.entityPath + '?_format=hal_json';
    let type = item.data.type;
    let datetime = item.data.datetime;
    let user = await this.currentUser();
    let username = user.name;
    let title = '';
    switch (type) {
      case 'hours':
        title = 'Available at ' + datetime;
      break;
      case 'sessions':
        title = item.data.title + ' & ' + username + ' at ' + datetime;
      break;
    }
    let data = {
      "_links": {
        "type":{
          "href": this.paths.domain + this.paths.restTypePath + '/' + type,
        }
      },
      "title": [{"value": title}],
      "body": [{"value": item.data.description}],
      "field_time": [{"value": datetime}],
      "field_counselor": [{"target_id": item.data.counselor}],
    };
    if (type=='sessions') {
      data.field_patient = [{
        target_id: this.selectedPatient
      }];
    }

    if (this.debug) {
      let auth = {
        username: this.credentials.user,
        password: this.credentials.pass
      };
      var headers = {
        'Content-Type': 'application/hal+json',
        'X-CSRF-Token': this.xCSRFToken,
      };
      var params = {
        url: url,
        method: 'post',
        //timeout: 8000,
        headers: headers,
        auth: auth,
        data: data
      };
    } else {
      let headers = {
        'Content-Type': 'application/hal+json',
        'X-CSRF-Token': this.xCSRFToken,
      };
      var params = {
        url: url,
        method: 'post',
        //timeout: 8000,
        headers: headers,
        data: data
      };
    }

    try {
        let res = await axios(params);
          return res.data
      }
      catch (err) {
          console.error(err);
      }
  }

  async removeEventFromBackend(item) {
    let type = item.data.type;
    let url = this.paths.domain + '/node/' + item.data.nid + '?_format=hal_json';
    let headers = {
      'Content-Type': 'application/hal+json',
      'X-CSRF-Token': this.xCSRFToken,
    };
    let params = {
      url: url,
      method: 'delete',
    //  timeout: 8000,
      headers: headers,
    };
    if (this.debug) {
      let auth = {
        username: this.credentials.user,
        password: this.credentials.pass
      };
      params.auth = auth;
    }
    try {
        let res = await axios(params);
        return res.data;
      }
      catch (err) {
        console.error(err);
      }
  }

  loadPatients() {
    axios
      .get(
        this.paths.domain + this.paths.patientsList
      )
      .then((response) => {

        this.patients = [{
          value: 0,
          text: ' - ',
        }];
        this.patients = this.patients.concat(response.data);
      })
      .catch(err => console.log(err));
  }

}
